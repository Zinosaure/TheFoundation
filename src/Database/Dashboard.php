<?php

/**
 *
 */
namespace src\Database;

use src\RouterHttp;
use src\RouterHttp\Response\Template;

/**
 * 
 */
abstract class Dashboard extends Entity {

    /**
     * o
     */
    const DASHBOARD_TITLE = null;
    const DASHBOARD_UPDATE_SFORMAT = '<a href="/dashboard/%s?do=update&args[]=%s">%s</a>';
    const DASHBOARD_DELETE_SFORMAT = '<a href="/dashboard/%s?do=delete&args[]=%s">%s</a>';
    
    /**
     * 
     */
    public static function sanitizePostData(array $data): array {
        foreach($data as $k => $v) {
            if (is_array($data[$k]))
                $data[$k] = self::sanitizePostData($data[$k]);
            else if (is_string($data[$k]) && (($data[$k] = trim($data[$k]))) == '')
                $data[$k] = null;
    
            if (is_numeric($data[$k]) || is_int($data[$k]))
                $data[$k] = (int) $data[$k];
        }
    
        return $data;
    }

    /**
     * 
     */
    public static function setPaginations(int $total_items, int $current = 1, int $length = 10): array {
        if ($current > ($max = ceil($total_items / $length)))
            $current = $max;
        else if ($current < 1)
            $current = 1;

        if ($max == 0 || $total_items == 0)
            $pagingations = [];
        else if ($max <= 10)
            $pagingations = range(1, $max);
        else if ($current <= 6)
            $pagingations = range(1, 11);
        else if ($current > $max - 5)
            $pagingations = range($max - 10, $max);
        else
            $pagingations = range($current - 5, $current + 5);
        
        return array_combine(array_values($pagingations), array_map(fn($v) => $v == $current ? 'active' : null, $pagingations));
    }

    /**
     * 
     */
    public function __setLabels(): array {
        return array_merge([
            'UPD' => 
                fn($e) => sprintf(static::DASHBOARD_UPDATE_SFORMAT, strtolower(metaphone(get_called_class())), $e->{static::ENTITY_PRIMARY_KEY}, 'UPD'),
            'DEL' =>
                fn($e) => sprintf(static::DASHBOARD_DELETE_SFORMAT, strtolower(metaphone(get_called_class())), $e->{static::ENTITY_PRIMARY_KEY}, 'DEL'),
        ], array_combine($fields = array_keys(static::ENTITY_FIELDS), array_map(function($field) {
            if (is_object($this->{$field}) || is_array($this->{$field}))
                return fn($e) => json_encode($e->{$field});
            
            return fn($e) => htmlspecialchars($e->{$field});
        }, $fields)));
    }

    /**
     * 
     */
    public function __setPDOStatement(): \PDOStatement {
        return static::$PDO->prepare(sprintf('SELECT 
                *,
                COUNT(*) OVER() AS __TOTAL_ITEMS__
            FROM 
                `%s` 
            ORDER BY 
                `%s` DESC
            LIMIT ?, ?;', static::ENTITY_TABLE_NAME, static::ENTITY_PRIMARY_KEY));
    }

    /**
     * 
     */
    public function __setPDOStatementParams(): array {
        return [];
    }

    /**
     * 
     */
    public function __setFieldsets(): array {
        $elements = [];

        foreach (static::ENTITY_FIELDS as $field => $type) {
            if ($field == self::ENTITY_PRIMARY_KEY)
                continue;

            if (in_array($type, ['int', 'float'])) {
                $elements[$field] = [
                    'type' => 'input',
                    'value' => $this->{$field},
                    'attributes' => [
                        'type' => 'number',
                    ],
                ];
            } else if (in_array($type, ['string'])) {
                $elements[$field] = [
                    'type' => 'textarea',
                    'value' => $this->{$field},
                    'attributes' => [
                        'rows' => 2,
                    ],
                ];
            } else if (in_array($type, ['bool'])) {
                $elements[$field] = [
                    'type' => 'select',
                    'value' => $this->{$field},
                    'options' => [0 => 'False', 1 => 'True'],
                ];
            } else if (in_array($type, ['list'])) {
                $elements[$field] = [
                    'type' => 'nitems',
                    'value' => $this->{$field},
                ];
            } else if (in_array($type, ['dict', 'object'])) {
                $elements[$field] = [
                    'type' => 'textarea',
                    'value' => @json_encode($this->{$field}, \JSON_PRETTY_PRINT) ?: $this->{$field},
                    'attributes' => [
                        'rows' => 8,
                    ],
                ];
            }
        }

        return $elements;
    }

    /**
     * 
     */
    public function __onSubmitPost() {
        foreach(self::sanitizePostData($_POST) as $name => $value)
            $this->__set($name, $value);

        if (!$this->save())
            throw new \Exception('SQLSTATE: ' . implode(' | ', static::$PDO->errorInfo()));

        return $this->{static::ENTITY_PRIMARY_KEY};
    }

    /**
     * 
     */
    public function __onSubmitDelete() {
        if (!$this->delete())
            throw new \Exception('SQLSTATE: ' . implode(' | ', static::$PDO->errorInfo()));

        return true;
    }
    
    /**
     * 
     */
    final public static function __init(RouterHttp $RouterHttp, array $navigations): void {
        $RouterHttp->map('get|post|delete', '/^dashboard\/(.*)?/', function($dashboard_id) use ($navigations) {

            $this->Response->Template = new Template(__DIR__ . '/html/template.phtml', [
                'navigations' => $navigations,
                'match' => (function(array $navigations) use ($dashboard_id): ?string {
                    foreach ($navigations as $items)
                        foreach ($items as $classname)
                            if ($dashboard_id == $classname::__id())
                                return $classname;
            
                    return null;
                })($navigations),
            ]);
            $this->Response->html();
        });
    }

    /**
     * 
     */
    final public static function __id(): string {
        return strtolower(metaphone(get_called_class()));
    }

    /**
     * 
     */
    final public static function __html(): string {
        $static = new static(...array_values((array) ($_GET['args'] ?? [])));
        $html = sprintf('<h1 class="mb-3 pb-1 border-bottom">%s</h1>', $static::DASHBOARD_TITLE);


        $show_exception = function(string ...$messsage): string {
            return sprintf('<div class="alert alert-danger"><h4>&cross; Oh shit!</h4> &#8627; %s</div>', sprintf(...$messsage));
        };
        
        $inline_attributes = function(array $attributes): string {
            return implode(' ', array_map(function($key, $value) {
                return sprintf('%s="%s"', htmlspecialchars($key), htmlspecialchars($value));
            }, array_keys($attributes), $attributes));
        };

        if (empty($do = ($_GET['do'] ?? null))) {
            if (empty($labels = $static->__setLabels()))
                return $html .= $show_exception('%s::__setLabels() is not implemented or return empty array.', get_called_class());

            $items = [];
            $total_items = 0;
            $current = $_GET['current'] ?? 1;
            $max_items = $_GET['maxitems'] ?? 10;
            $offset = ($current - 1) * $max_items;
    
            try {
                $stmt = $static->__setPDOStatement();
                $params = array_values($static->__setPDOStatementParams() + ['__offset' => $offset, '__max_items' => $max_items]);

                if (!preg_match('/LIMIT\s+\?\s*,\s*\?\s*;?\s*$/s', $stmt->queryString))
                    throw new \Exception('%s::__setPDOStatement(), `<b>LIMIT ?, ?</b>` must be included in the stetement!');

                if (!preg_match('/^\s*SELECT(.*)AS\s+__TOTAL_ITEMS__/s', $stmt->queryString))
                    throw new \Exception('%s::__setPDOStatement(), `<b>[subquery to calculate number of items] AS __TOTAL_ITEMS__</b>` must be included in SELECT projection!');

                if ($stmt->execute($params))
                    $items = $stmt->fetchAll(\PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE, get_called_class());

                $total_items = ($item = current($items)) ? $item->__TOTAL_ITEMS__ : count($items);
            } catch (\Exception $e) {
                $html .= $show_exception($e->getMessage(), get_called_class());
            }

            $html .= '<p class="mb-3">';
            $html .= sprintf('Displaying <b>%d</b>-<b>%d</b> on a total of <b>%d</b> item(s)', $total_items > 0 ? $offset + 1 : 0, min($total_items, $max_items * $current), $total_items);
        
            if (!empty($static->__setFieldsets()))
                $html .= sprintf('<a class="float-right px-4 py-1 btn btn-sm btn-info" href="/dashboard/%s?do=update&args[]=">Update</a>', $static::__id());
    
            $html .= '</p>';
            $html .= '<div class="table-responsive">';
            $html .= '<table class="table table-md table-bordered">';
            $html .= '<thead class="thead-light">';
            $html .= '<tr>';
            
            foreach($labels as $label => $callback)
                $html .= sprintf('<th scope="col">%s</th>', $label);
    
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            foreach ($items as $item) {
                $html .= '<tr>';
                
                foreach($labels as $label => $callback) {
                    $html .= '<td>';
    
                    if (is_callable($callback))
                        $html .= $callback($item);
                    else if (is_string($callback) || is_numeric($callback)) {
                        if (is_object($item) && $item->{$callback} ?? false)
                            $html .= (string) $item->{$callback};
                        else if (is_array($item) && $item[$callback] ?? false)
                            $html .= $item[$callback];
                        else
                            $html .= $callback;
                    } else
                        $html .= sprintf('<!-- %s: not implemented! -->', $label);
                    
                    $html .= '</td>';
                }
                
                $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
            $html .= '<div class="float-right my-3"><ul class="pagination">';

            unset($_GET['current']);
            foreach (self::setPaginations((int) $total_items, (int) $current, $max_items) as $nb => $active)
                $html .= sprintf('<li class="page-item %s"><a class="page-link" href="/dashboard/%s?%s&current=%s">%s</a></li>', $active, $static::__id(), urldecode(http_build_query($_GET)), $nb, $nb);
              
            return $html .= '</ul></div>';
        }
        
        if ($do == 'update') {
            if (empty($fieldsets = $static->__setFieldsets()))
                return $html .= $show_exception('%s::__setFieldsets() is not implemented or return empty array.', get_called_class());

            $html .= '<div class="row">';
            $html .= '<div class="col-7">';
            $html .= '<form method="post" enctype="multipart/form-data">';

            if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST') {
                try {
                    if ($ID = $static->__onSubmitPost())
                        exit(header(sprintf('Location: /dashboard/%s?do=update&args[]=%s&success=1', $static::__id(), $ID)));
                } catch (\Exception $e) {
                    $html .= $show_exception($e->getMessage());
                }
            } else if (!empty($_GET['success']))
                $html .= '<div class="alert alert-success"><h5>&check; Well done!</h5> &#8627; Your changes has been successfully saved!</div>';

            foreach ($fieldsets as $name => $options) { 
                $options = array_replace_recursive($schema = [
                    'type' => 'input',
                    'label' => htmlspecialchars($name),
                    'value' => null,
                    'helptext' => null,
                    'attributes' => [
                        'type' => 'text',
                        'class' => 'form-control', 
                    ],
                    'options' => [],
                ], array_intersect_key($options, $schema), ['attributes' => ['name' => $name = htmlspecialchars($name)]]);

                if (is_callable($options['value']))
                    $options['value'] = $options['value']($static);

                if (($input_type = strtolower($options['type'])) == 'hidden') {
                    $options['attributes']['type'] = 'hidden';
                    $html .= sprintf('<input value="%s" %s />', htmlspecialchars($options['value']), $inline_attributes($options['attributes']));
                    continue;
                }
                
                $html .= '<div class="form-group">';
                $html .= sprintf('<label class="control-label font-weight-bold">%s</label>', $options['label']);
                $html .= '<div class="control-input">';
                    
                switch ($input_type) {
                    case 'text':
                    case 'input':
                            $html .= sprintf('<input value="%s" %s />', htmlspecialchars($options['value']), $inline_attributes($options['attributes']));
                        break;

                    case 'checkbox':
                            $options['attributes']['type'] = 'checkbox';
                            $options['attributes']['class'] = 'custom-control-input';
                
                            foreach ($options['options'] as $optvalue => $optlabel) {
                                $id = 'i-' . md5($optvalue);
                                $checked = in_array($optvalue, (array) $options['value']) ? 'checked=""' : null;
            
                                $html .= '<div class="custom-control custom-checkbox">';
                                $html .= sprintf('<input id="%s" value="%s" %s %s />', $id, htmlspecialchars($optvalue), $inline_attributes($options['attributes']), $checked);
                                $html .= sprintf('<label class="custom-control-label" for="%s">%s</label>', $id, $optlabel);
                                $html .= '</div>';
                            }
                        break;

                    case 'textarea':
                            $html .= sprintf('<textarea %s>%s</textarea>', $inline_attributes($options['attributes']), $options['value']);
                        break;

                    case 'radio':
                    case 'select':
                            $options['attributes']['class'] = 'custom-select';
                            $html .= sprintf('<select %s>', $inline_attributes($options['attributes']));

                            foreach ($options['options'] as $optvalue => $optlabel) {
                                $selected = in_array($optvalue, (array) $options['value']) ? 'selected=""' : null;
                                $html .= sprintf('<option value="%s" %s>%s</label>', htmlspecialchars($optvalue), $selected, $optlabel);
                            }
                            
                            $html .= '</select>';
                        break;

                    case 'upload':
                            $uqid = 'i-' . md5($name);
                            $options['attributes']['type'] = 'file';
                            $options['attributes']['multiple'] = false;
                            $options['attributes']['onchange'] = sprintf('return (function(event) {
                                if (!event.target.files.length)
                                    return $("span#uploadmark-%s").html("[WILL NOT CHANGE]");

                                $("span#uploadmark-%s").html(event.target.files[0].name);
                            })(event);', $uqid, $uqid);
                            $html .= sprintf('<input %s />', $inline_attributes($options['attributes']));
                            $html .= '<div class="mt-1">';
                            $html .= '<small class="help-text">';
                            
                            if ($options['value'])
                                $html .= sprintf('<a href="%s" target="_blank">%s</a>', $options['value'], basename($options['value']));
                            else
                                $html .= '[NO FILE PREVIOUSLY UPLOADED]';
                            
                            $html .= sprintf(' &rarr; <span id="uploadmark-%s">[WILL NOT CHANGE]</span></small>', $uqid);
                            $html .= '</div>';
                        break;

                    case 'nitems':
                    case 'nitems-input':
                            $options['attributes']['disabled'] = true;

                            $html .= sprintf('<div class="nitems" data-name="%s" data-items="%s">', $name, htmlspecialchars(json_encode((array) $options['value'])));
                            $html .= sprintf('<input %s />', $inline_attributes($options['attributes']));
                            $html .= '<div class="mt-2 text-right">';
                            $html .= sprintf('<a class="btn btn-sm btn-secondary" onclick="return ui.nitems.text.push(this, `%s`);">Push item</a>', $name);
                            $html .= '</div>';
                            $html .= '<ul class="nitems-ul mt-2 pl-3" style="max-height: 200px; overflow-y: auto;"></ul>';
                            $html .= '</div>';
                        break;
                    
                    case 'nitems-text':
                    case 'nitems-textarea':
                            $options['attributes']['row'] = 5;
                            $options['attributes']['disabled'] = true;

                            $html .= sprintf('<div class="nitems" data-name="%s" data-items="%s">', $name, htmlspecialchars(json_encode((array) $options['value'])));
                            $html .= sprintf('<textarea %s></textarea>', $inline_attributes($options['attributes']));
                            $html .= '<div class="mt-2 text-right">';
                            $html .= sprintf('<a class="btn btn-sm btn-secondary" onclick="return ui.nitems.text.push(this, `%s`);">Push item</a>', $name);
                            $html .= '</div>';
                            $html .= '<ul class="nitems-ul mt-2 pl-3" style="max-height: 200px; overflow-y: auto;"></ul>';
                            $html .= '</div>';
                        break;

                    case 'nitems-select':
                    case 'nitems-selectbox':
                            $options['value'] = array_intersect_key($options['options'], array_flip((array) $options['value']));
                            $options['attributes']['class'] = 'custom-select';
                            $options['attributes']['disabled'] = true;

                            $html .= sprintf('<div class="nitems is-selectbox" data-name="%s" data-items="%s">', $name, htmlspecialchars(json_encode((array) $options['value'])));
                            $html .= sprintf('<select %s>', $inline_attributes($options['attributes']));

                            if (!isset($options['options'][null]))
                                $options['options'] = [null => null] + $options['options'];

                            foreach ($options['options'] as $optvalue => $optlabel)
                                $html .= sprintf('<option value="%s">%s</label>', htmlspecialchars($optvalue), $optlabel);
                            
                            $html .= '</select>';
                            $html .= '<div class="mt-2 text-right">';
                            $html .= sprintf('<a class="btn btn-sm btn-secondary" onclick="return ui.nitems.select.push(this, `%s`);">Push item</a>', $name);
                            $html .= '</div>';
                            $html .= '<ul class="nitems-ul mt-2 pl-3" style="max-height: 200px; overflow-y: auto;"></ul>';
                            $html .= '</div>';
                        break;

                    case 'html':
                    case 'custom':
                            $html .= $options['value'];
                        break;

                    default:
                        $html .= $show_exception('Unknown Field Type: "%s" for property "%s"', $options['type'], $name);
                }

                if (!is_null($options['helptext']))
                    $html .= sprintf('<small class="help-text">%s</small>', $options['helptext']);

                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '<div class="form-group text-right mt-3 pt-3 border-top">';
            $html .= sprintf('<a class="float-left btn btn-danger px-3" href="/dashboard/%s">Back</a>', $static::__id());
            $html .= sprintf('<a class="float-left btn btn-danger px-3 ml-2" href="/dashboard/%s?do=update&args[]=">Restart</a>', $static::__id());
            $html .= '<button type="submit" class="btn btn-primary px-5"><div class="px-5">Save</div></button>';
            $html .= '</div>';

            $html .= '</form>';
            $html .= '</div>';
            $html .= '<div class="col-5"></div>';
            
            return $html .= '</div>';
        }
        
        if (is_callable([$static, $callback = '__html_' . $do]))
            return (string) $static->{$callback}();
        
        return $html .= $show_exception('%s::__html_%s() is not implemented.', get_called_class(), $do);
    }
}
?>
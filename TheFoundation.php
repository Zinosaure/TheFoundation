<?php

/**
*
*/
define('APPLICATION_PATH', __DIR__ . '/../');

/**
*
*/
spl_autoload_register(function(string $classname) {
    if (file_exists($filename = __DIR__ . '/packages/' . str_replace('\\', '/', $classname) . '.php'))
        return require_once $filename;

    return false;
});

/**
 * 
 */
foreach([
	__DIR__ . '/TheFoundation/RouterHttp.php',
	__DIR__ . '/TheFoundation/RouterHttp/Response.php',
	__DIR__ . '/TheFoundation/RouterHttp/Response/Template.php',
	__DIR__ . '/TheFoundation/Request.php',
	__DIR__ . '/TheFoundation/PDOFactory.php',
	__DIR__ . '/TheFoundation/Database/Entity.php',
	__DIR__ . '/TheFoundation/Database/Dashboard.php',
] as $classname)
	require_once $classname;
?>
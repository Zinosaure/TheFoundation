<?php

/**
*
*/
define('APPLICATION_PATH', __DIR__ . '/');

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
	__DIR__ . '/src/RouterHttp.php',
	__DIR__ . '/src/RouterHttp/Response.php',
	__DIR__ . '/src/RouterHttp/Response/Template.php',
	__DIR__ . '/src/Request.php',
	__DIR__ . '/src/PDOFactory.php',
	__DIR__ . '/src/Database/Entity.php',
	__DIR__ . '/src/Database/Dashboard.php',
] as $classname)
	require_once $classname;
?>
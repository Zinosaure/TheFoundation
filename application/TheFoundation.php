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
	__DIR__ . '/#Foundation/RouterHttp.php',
	__DIR__ . '/#Foundation/RouterHttp/Response.php',
	__DIR__ . '/#Foundation/RouterHttp/Response/Template.php',
	__DIR__ . '/#Foundation/Request.php',
	__DIR__ . '/#Foundation/PDOFactory.php',
	__DIR__ . '/#Foundation/Database/Entity.php',
	__DIR__ . '/#Foundation/Database/Dashboard.php',
] as $classname)
	require_once $classname;
?>
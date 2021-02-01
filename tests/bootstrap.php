<?php
// test
/**
 * Use the DS to separate the directories in other defines
 */
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

/**
 * The full path to the directory which holds "src", WITHOUT a trailing DS.
 */
define('ROOT', dirname(__DIR__));

/**
 * Path to the tests directory.
 */
define('TESTS', ROOT . DS . 'tests' . DS);

/**
 * Uncomment block of code below if you want to use `.env` file during development.
 * You should copy `.env.default to `.env` and set/modify the
 * variables as required.
 */
if (!getenv('BEDITA_API') && file_exists(TESTS . '.env')) {
    $dotenv = new \josegonzalez\Dotenv\Loader([TESTS . '.env']);
    $dotenv->parse()
        ->putenv()
        ->toEnv()
        ->toServer();
}

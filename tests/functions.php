<?php

if (!function_exists('storage_path')) {
    function storage_path($path = '')
    {
        return sys_get_temp_dir() . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
    }
}

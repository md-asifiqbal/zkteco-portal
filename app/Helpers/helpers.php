<?php

if (! function_exists('tenant')) {
    function tenant()
    {
        return app('tenant');
    }
}

if (! function_exists('tenant_id')) {
    function tenant_id()
    {
        return app()->bound('tenant') ? app('tenant')->id : null;
    }
}

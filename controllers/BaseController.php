<?php

namespace App\Controller;

abstract class BaseController
{
    var $app;

    abstract function getRoutes();


    /**
     *
     * @return mixed
     * @deprecated use $app from first argument
     */
    function getApp(){
        return $this->app;
    }
}
<?php
namespace JeffBdn\ToolsBundle\Service;

class Math
{
    public function random(){
        return mt_rand(0,100);
    }
}
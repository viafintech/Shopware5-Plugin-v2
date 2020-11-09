<?php

namespace ZerintBarzahlenViacash\Request;

use ZerintBarzahlenViacash\Request\Validate;
use ZerintBarzahlenViacash\Request\Autocorrect;
use ZerintBarzahlenViacash\Request\Sanitize;

class CreatePing extends Request
{
    /**
     * @var string
     */
    protected $path = '/ping';

    /**
     * @var string
     */
    protected $method = 'POST';


}

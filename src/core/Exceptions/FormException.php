<?php

namespace CI\core\Exceptions;

use CI\core\Error;

class FormException extends JuniorException
{
    public function __construct(array $error_array = [])
    {
        if (empty($error_array)) {
            parent::__construct(Error::getInstance('miss_form_rule'));
        } else {
            parent::__construct(Error::getInstance('input_validate', $error_array));
        }
    }

    public function getErrorArray()
    {
        return $this->err->getResults();
    }
}
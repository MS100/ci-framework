<?php

namespace CI\core\Output\Format;

use CI\core\Output\Response;

class Image extends Response
{
    public function __construct(string $image, string $mime = '')
    {
        if (empty($mime)) {
            $size = getimagesizefromstring($image);
            $mime = $size['mime'];
        }
        $this->headers['Content-Type'] = $mime;
        $this->body = $image;
    }
}
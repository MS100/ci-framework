<?php

namespace CI\core\Output\Format;

use CI\core\Output\Response;

class Download extends Response
{
    public function __construct(string $name, string $content, int $update_time = 0, string $mime = '')
    {
        if (empty($mime)) {
            $mime = get_mime($name);
        }

        $this->headers['Content-Type'] = $mime . '; charset=utf-8';
        //$this->headers['Content-Type'] = 'application/octet-stream';
        //$this->headers['Accept-Ranges'] = 'bytes';
        //$this->headers['Content-Transfer-Encoding'] = 'binary';
        $this->headers['Content-Disposition'] = 'attachment; filename=' . urlencode($name);
        //$this->headers['Content-Length'] = strlen($content);

        if ($update_time) {
            $this->headers['Last-Modified'] = date('r', $update_time);
        } else {
            $this->headers['Last-Modified'] = date('r', time());
            $this->headers['Expires'] = '0';
            $this->headers['Cache-Control'] = 'private, no-transform, no-store, must-revalidate';
        }

        $this->body = $content;
    }
}
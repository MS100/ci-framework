<?php

namespace CI\libraries\DB\Drivers;

class_alias(\CI_DB_query_builder::class, 'CI_DB', true);

class Mysqli extends \CI_DB_mysqli_driver
{
    public function __construct($params)
    {
        parent::__construct($params);
        $this->initialize();
    }

    /**
     * Return trans_depth
     *
     * @return int
     */
    public function trans_depth()
    {
        return $this->_trans_depth;
    }

    protected function _chunk_array($array)
    {
        $count = count($array);

        return array_chunk($array, ceil($count / (($count >> 10) + 1)));
    }

    public function where_in($key = null, $values = null, $escape = null)
    {
        if (is_array($values) && count($values) >= 1024) {
            $array_chunk = $this->_chunk_array($values);
            $this->group_start();
            foreach ($array_chunk as $chunk) {
                parent::or_where_in($key, $chunk, $escape);
            }
            $this->group_end();

            return $this;
        } else {
            return parent::where_in($key, $values, $escape);
        }
    }

    public function or_where_in($key = null, $values = null, $escape = null)
    {
        if (is_array($values) && count($values) >= 1024) {
            $array_chunk = $this->_chunk_array($values);
            foreach ($array_chunk as $chunk) {
                parent::or_where_in($key, $chunk, $escape);
            }

            return $this;
        } else {
            return parent::or_where_in($key, $values, $escape);
        }
    }

    public function where_not_in($key = null, $values = null, $escape = null)
    {
        if (is_array($values) && count($values) >= 1024) {
            $array_chunk = $this->_chunk_array($values);
            foreach ($array_chunk as $chunk) {
                parent::where_not_in($key, $chunk, $escape);
            }

            return $this;
        } else {
            return parent::where_not_in($key, $values, $escape);
        }
    }

    public function or_where_not_in($key = null, $values = null, $escape = null)
    {
        if (is_array($values) && count($values) >= 1024) {
            $array_chunk = $this->_chunk_array($values);
            $this->or_group_start();
            foreach ($array_chunk as $chunk) {
                $this->where_not_in($key, $chunk, $escape);
            }
            $this->group_end();

            return $this;
        } else {
            return parent::or_where_not_in($key, $values, $escape);
        }
    }
}

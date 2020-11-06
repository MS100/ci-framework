<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2019, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package    CodeIgniter
 * @author    EllisLab Dev Team
 * @copyright    Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright    Copyright (c) 2014 - 2019, British Columbia Institute of Technology (https://bcit.ca/)
 * @license    https://opensource.org/licenses/MIT	MIT License
 * @link    https://codeigniter.com
 * @since    Version 1.0.0
 * @filesource
 */
return [

    'db_invalid_connection_str' => 'Unable to determine the database settings based on the connection string you submitted.',
    'db_unable_to_connect' => 'Unable to connect to your database server using the provided settings.',
    'db_unable_to_select' => 'Unable to select the specified database: %s',
    'db_unable_to_create' => 'Unable to create the specified database: %s',
    'db_invalid_query' => 'The query you submitted is not valid.',
    'db_must_set_table' => 'You must set the database table to be used with your query.',
    'db_must_use_set' => 'You must use the "set" method to update an entry.',
    'db_must_use_index' => 'You must specify an index to match on for batch updates.',
    'db_batch_missing_index' => 'One or more rows submitted for batch updating is missing the specified index.',
    'db_must_use_where' => 'Updates are not allowed unless they contain a "where" clause.',
    'db_del_must_use_where' => 'Deletes are not allowed unless they contain a "where" or "like" clause.',
    'db_field_param_missing' => 'To fetch fields requires the name of the table as a parameter.',
    'db_unsupported_function' => 'This feature is not available for the database you are using.',
    'db_transaction_failure' => 'Transaction failure: Rollback performed.',
    'db_unable_to_drop' => 'Unable to drop the specified database.',
    'db_unsupported_feature' => 'Unsupported feature of the database platform you are using.',
    'db_unsupported_compression' => 'The file compression format you chose is not supported by your server.',
    'db_filepath_error' => 'Unable to write data to the file path you have submitted.',
    'db_invalid_cache_path' => 'The cache path you submitted is not valid or writable.',
    'db_table_name_required' => 'A table name is required for that operation.',
    'db_column_name_required' => 'A column name is required for that operation.',
    'db_column_definition_required' => 'A column definition is required for that operation.',
    'db_unable_to_set_charset' => 'Unable to set client connection character set: %s',
    'db_error_heading' => 'A Database Error Occurred',
];
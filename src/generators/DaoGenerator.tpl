<?='<?php'?>


namespace <?=strtr($app, '/', '\\')?>\daos<?=$dir===''?'':'\\'.strtr($dir, '/', '\\')?>;

use CI\core\dao\mysql_Dao_<?=$cache ? '' : 'no_' ?>cache;

class Dao_<?=$table?> extends mysql_Dao_<?=$cache ? '' : 'no_' ?>cache
{
    protected static $_table = '<?=$db . '.' . $table?>';
    protected static $_active_group = '';
<?php if($cache): ?>
    protected static $_cache_group = '';
<?php endif ?>

    protected static $_insert_fields = [
<?php foreach($insert_fields as $k => $v): ?>
        <?=var_export($k, true) ?> => <?=var_export($v, true) ?>,
<?php endforeach ?>
    ];

    protected static $_update_fields = [
<?php foreach($update_fields as $k => $v): ?>
        <?=var_export($k, true) ?> => <?=var_export($v, true) ?>,
<?php endforeach ?>
    ];

    protected static $_select_fields = [
<?php foreach($select_fields as $v): ?>
        <?=var_export($v, true) ?>,
<?php endforeach ?>
    ];

    protected static $_idx = [<?php if(empty($idx)): ?>];
<?php else: ?>

<?php foreach($idx as $i): ?>
        //['where' => [<?php $idx_str = ''; foreach($i as $m => $n){ $idx_str .= var_export($m, true) . " => '', ";} echo trim($idx_str) ?>], 'sort' => [], 'limit' => 400],
<?php endforeach ?>
    ];
<?php endif ?>

    protected static $_udx = [<?php if(empty($udx)): ?>];
<?php else: ?>

<?php foreach($udx as $u): ?>
        [<?php $udx_str = ''; foreach($u as $m => $n){ $udx_str .= var_export($m, true) . " => '', ";} echo trim($udx_str) ?>],
<?php endforeach ?>
    ];
<?php endif ?>

    protected static $_created_time_key = <?=var_export($created_time_key, true); ?>;
    protected static $_updated_time_key = <?=var_export($updated_time_key, true); ?>;
    protected static $_primary_key = <?=var_export($primary_key, true) ?>;
    protected static $_auto_pk = <?=var_export($auto_pk, true); ?>;

}


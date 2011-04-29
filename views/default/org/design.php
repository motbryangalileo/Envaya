<?php
    $org = $vars['org'];
?>

<form action='<?php echo $org->get_url() ?>/design' method='POST'>

<?php echo view('input/securitytoken'); ?>

<div class='section_header' id='icon'><?php echo __('design:logo'); ?></div>
<div class='section_content padded'>
<div class='help' style='padding-bottom:5px'>
<?php echo __('design:logo:description') ?>
</div>


<?php

echo view("input/image",
    array(
        'current' => $org->get_icon('medium'),
        'trackDirty' => true,
        'sizes' => User::get_icon_sizes(),
        'removable' => $org->has_custom_icon(),
        'thumbnail_size' => 'medium',
        'name' => 'icon',
        'deletename' => 'deleteicon',
    ))

?>

<?php
echo view('input/submit',array(
    'value' => __('savechanges'),
    'trackDirty' => true,
));

?>

</div>

<div class='section_header'><?php echo __('design:header'); ?></div>
<div class='section_content padded'>

<script type='text/javascript'>

function getSelectedRadio($name)
{
    var $buttons = document.getElementsByName($name);
    for (var $i = 0; $i < $buttons.length; $i++)
    {
        var $button = $buttons[$i];
        if ($button.checked)
        {
            return $button.value;
        }
    }
    return $null;
}

function customHeaderChanged()
{
    setTimeout(function() {
        var $customDiv = document.getElementById('custom_header_container');
        var $defaultDiv = document.getElementById('default_header_container');
        var $value = getSelectedRadio('custom_header');

        if ($value == '1')
        {
            $customDiv.style.display = 'block';
            $defaultDiv.style.display = 'none';
        }
        else
        {
            $customDiv.style.display = 'none';
            $defaultDiv.style.display = 'block';
        }
    }, 1);
}
</script>

<?php
    echo view('input/radio', array(
        'name' => 'custom_header',
        'value' => $org->has_custom_header() ? '1' : '0',
        'js' => "onchange='customHeaderChanged()' onclick='customHeaderChanged()'",
        'options' => array(
            '0' => __('design:header:default'),
            '1' => __('design:header:custom'),
        )
    ));
?>

<div id='default_header_container' <?php echo $org->has_custom_header() ? "style='display:none'" : "" ?> >
    <div class='header_preview'>
        <?php echo view('org/default_header', array('org' => $org, 'title' => __('design:header:subtitle'))) ?>
    </div>
    <div class='help'><?php echo sprintf(__('design:header:changelogo'), __('design:logo')) ?></div>
</div>

<div id='custom_header_container' <?php echo !$org->has_custom_header() ? "style='display:none'" : "" ?>>

    <?php
        if ($org->has_custom_header())
        {
            echo "<div style='margin-top:10px'>".__('upload:image:current')."</div>";
            echo "<div class='header_preview'>".view('org/custom_header', array('org' => $org))."</div>";
        }
    ?>

    <div class='input'>
            <?php
                if ($org->has_custom_header())
                {
                    echo __('upload:image:new');
                }
                else
                {
                    echo __('design:header:chooseimage');
                }
            ?>
        <br />
    <?php

    echo view("input/swfupload_image",
        array(
            'trackDirty' => true,
            'sizes' => User::get_header_sizes(),
            'thumbnail_size' => 'large',
            'name' => 'header',
        ))
    ?>
    <div class='help'>
    <?php echo __('design:header:constraints') ?>
    </div>
    </div>
</div>


<?php
echo view('input/submit',array(
    'value' => __('savechanges'),
    'trackDirty' => true,
));

?>

</div>


<div class='section_header'><?php echo __('design:theme'); ?></div>
<div class='section_content padded'>

<?php echo view('input/theme', array(
    'name' => 'theme',
    'value' => $org->theme,
    'options' => Theme::available_names(),
    'previewUrl' => $org->get_url()
)); ?>

<?php
echo view('input/hidden', array('name' => 'guid', 'value' => $org->guid));

echo view('input/submit',array(
    'value' => __('savechanges'),
    'trackDirty' => true,
));
?>
</div>
</form>

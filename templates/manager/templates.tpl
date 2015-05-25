{"<?php"}
{if !empty( $instance_parent )}
{if $instance_parent == 'common'}
include ROOT_PATH . '/vendor/sifophp/sifo-common-instance/config/{$file_name}';
{else}
include ROOT_PATH . '/instances/{$instance_parent}/config/{$file_name}';
{/if}
{/if}
{foreach from=$config item=c key=k}
	{if is_array( $c ) }
		{foreach from=$c item=path key=instance}
$config['{$k}']['{$instance}'] = '{$path}';
		{/foreach}
	{else}
$config['{$k}'] = '{$c}';
	{/if}
{/foreach}

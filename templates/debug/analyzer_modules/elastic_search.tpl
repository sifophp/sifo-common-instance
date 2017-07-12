{if is_array($debug.elastic_search)}
	<h2 id="elastic_search_queries">{t}Elastic Search{/t}</h2>
{foreach $debug.elastic_search as $index => $search}
	<h3 class="queries query_read{if $search.error} query_error{/if}" id="elastic_search_{$index}"><a class="debug_toggle_view" rel="elastic_search_content_{$index}{$execution_key}" href="#">{$index+1}. {$search.tag}</a> <small>({$search.time|time_format} - match: {$search.number_of_rows|default:''} elements - return: {$search.number_of_rows|default:''} elements )</small></h3>
	<div id="elastic_search_content_{$index}{$execution_key}" class="debug_contents">

		<table>
			<tr>
				<th>Cluster id</th>
				<th>Cluster name</th>
				<th>ElasticSearch Version</th>
				<th>Trace <a href="#" class="debug_toggle_view" rel="elastic_search_backtrace_{$index}{$execution_key}">(show/collapse full trace)</a></th>
			</tr>
			<tr>
				<td>{$search.connection_data.cluster_uuid|default:''}</td>
				<td>{$search.connection_data.cluster_name|default:''}</td>
				<td>{$search.connection_data.version.number}</td>
				<td>
                    <div id="elastic_search_backtrace_{$index}{$execution_key}" class="debug_contents">
                    {foreach from=$search.backtrace item=step name=backtrace_iterator}

                        {if !$smarty.foreach.backtrace_iterator.last}{$step}<br />{/if}
                    {/foreach}
                    </div>
                    {$search.backtrace[$search.backtrace|count - 1]}
                </td>
			</tr>
		</table>

		{if !empty( $search.error )}
		<h4 class="query_error">{$search.error}</h4>
		{/if}
		{if !empty( $query.error )}<p class="query_error"><b>{$search.error}</b></p>{/if}
		<pre>{$search.query.body}</pre>

	{if !empty( $search.results )}
		<table>
			<tr>
			{foreach $search['results'][0]['_source'] as $attribute => $value}
				<th>{$attribute}</th>
			{/foreach}

			</tr>
			{foreach $search['results'] as $result}
				<tr>
				{foreach $result['_source'] as $value}
					<td>{if is_array($value)}{$value|debug_print_var|truncate:300}{else}{$value}{/if}</td>
				{/foreach}
				</tr>
			{/foreach}
		</table>
	{else}
		<h5>No results.</h5>
	{/if}
	</div>
{/foreach}
{/if}

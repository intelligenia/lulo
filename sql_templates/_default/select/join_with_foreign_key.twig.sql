{% import "escaping/escape.twig.sql" as escape %}

{# JOIN for foreign-key relationships #}
LEFT OUTER JOIN {{escape.table(relationship["table"])}} AS {{relationship["name"]}} ON (
	{% for src_attr,dest_attr in relationship["attributes"]["condition"] %}
		{{relationship["name"]}}.{{escape.field(dest_attr)}} = {{query.table_alias}}.{{escape.field(src_attr)}}
		{% if not loop.last %}AND{% endif %}
	{% endfor %}
)

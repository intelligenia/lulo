{# JOIN for foreign-key relationships #}
LEFT OUTER JOIN {{relationship["table"]}} AS {{relationship["name"]}} ON (
	{% for src_attr,dest_attr in relationship["attributes"]["condition"] %}
		{{relationship["name"]}}.{{dest_attr}} = main_table.{{src_attr}}
		{% if not loop.last %}AND{% endif %}
	{% endfor %}
)

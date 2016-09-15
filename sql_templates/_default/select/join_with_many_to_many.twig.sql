{% import "escaping/escape.twig.sql" as escape %}

{# Many-to-Many relationship #}
{% set condition_i = 0 %}

{# Number of nexus tables #}
{% set num_junctions = relationship["attributes"]["junctions"]|length %}

{% for junction in relationship["attributes"]["junctions"] %}
	LEFT OUTER JOIN {{escape.table(junction)}} ON (
		{% for src_attr,dest_attr in relationship["attributes"]["conditions"][condition_i] %}
			{% if condition_i > 0 %}
                            {% set src_table = escape.table(relationship["attributes"]["junctions"][condition_i]) %}
			{% endif %}
			{{query.table_alias}}.{{escape.field(src_attr)}} = {{junction}}.{{escape.field(dest_attr)}}
			{% if not loop.last %} AND {% endif %}
		{% endfor %}
	)
		{% set condition_i = condition_i + 1 %}
{% endfor %}

{% set condition_i = condition_i  %}
LEFT OUTER JOIN {{escape.table(relationship["table"])}} AS {{relationship["name"]}} ON (
	{% for junction_attr,next_attr in relationship["attributes"]["conditions"][condition_i] %}
		{% set nexus_table = relationship["attributes"]["junctions"][condition_i-1] %}
		{{escape.table(nexus_table)}}.{{escape.field(junction_attr)}} = {{relationship["name"]}}.{{escape.field(next_attr)}}
		{% if not loop.last %} AND {% endif %}
	{% endfor %}
)

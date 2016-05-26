{# Many-to-Many relationship #}
{% set condition_i = 0 %}

{# Number of nexus tables #}
{% set num_junctions = relationship["attributes"]["junctions"]|length %}

{% for junction in relationship["attributes"]["junctions"] %}
	LEFT OUTER JOIN {{junction}} ON (
		{% for src_attr,dest_attr in relationship["attributes"]["conditions"][condition_i] %}
			{% set src_table = 'main_table' %}
			{% if condition_i > 0 %}
				{% set src_table = relationship["attributes"]["junctions"][condition_i] %}
			{% endif %}
			{{src_table}}.{{src_attr}} = {{junction}}.{{dest_attr}}
			{% if not loop.last %} AND {% endif %}
		{% endfor %}
	)
		{% set condition_i = condition_i + 1 %}
{% endfor %}

{% set condition_i = condition_i  %}
LEFT OUTER JOIN {{relationship["table"]}} AS {{relationship["name"]}} ON (
	{% for junction_attr,next_attr in relationship["attributes"]["conditions"][condition_i] %}
		{% set nexus_table = relationship["attributes"]["junctions"][condition_i-1] %}
		{{nexus_table}}.{{junction_attr}} = {{relationship["name"]}}.{{next_attr}}
		{% if not loop.last %} AND {% endif %}
	{% endfor %}
)

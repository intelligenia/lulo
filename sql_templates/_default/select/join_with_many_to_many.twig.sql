{% import "escaping/escape.twig.sql" as escape %}

{# Many-to-Many relationship #}
{% set condition_i = 0 %}

{# Number of nexus tables #}
{% set num_junctions = relationship["attributes"]["junctions"]|length %}

{% set tables = ["_main_table"] | merge(relationship["attributes"]["junctions"]) %}

{% for table_i,tableT in tables %}
  {%if not loop.last %}
    {% set src_table = tableT %}
    {% set dest_table = tables[(table_i+1)] %}
    LEFT OUTER JOIN {{escape.table(dest_table)}} ON (
  {% for src_attr,dest_attr in relationship["attributes"]["conditions"][table_i] %}
   {{escape.table(src_table)}}.{{escape.field(src_attr)}} = {{escape.table(dest_table)}}.{{escape.field(dest_attr)}}
   {% if not loop.last %} AND {% endif %}
  {% endfor %}
 )
  {%endif%}
{%endfor%}

{% set condition_i = ((tables | length) - 1) %}

LEFT OUTER JOIN {{escape.table(relationship["table"])}} AS {{relationship["name"]}} ON (
 {% for junction_attr,next_attr in relationship["attributes"]["conditions"][condition_i] %}
  {% set nexus_table = relationship["attributes"]["junctions"][condition_i-1] %}
  {{escape.table(nexus_table)}}.{{escape.field(junction_attr)}} = {{escape.table(relationship["name"])}}.{{escape.field(next_attr)}}
  {% if not loop.last %} AND {% endif %}
 {% endfor %}
)
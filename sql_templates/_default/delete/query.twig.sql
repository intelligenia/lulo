{% extends "select/query.twig.sql" %}


{% block select %}
  DELETE main_table.*
  
  {# Every child table that has the main table as a foreign key must be included with an alias. These tables will be deleted if have delete on cascade. #}
  {% for relationship in query.relationships if relationship["attributes"]["type"]=="OneToMany" %}
    ,
    {% for src_attr,dest_attr in relationship["attributes"]["condition"] %}
      {{relationship["name"]}}
    {% endfor %}
  {% endfor %}
  
  {# Nexus tables of the Many-to-many relationships of the main table (with cascade deletion). #}
  {% for relationship in query.relationships if relationship["attributes"]["type"]=="ManyToMany" %}
    ,
    {% for junction in relationship["attributes"]["junctions"] %}
      {{junction}}
    {% endfor %}
  {% endfor %}
  
{% endblock select %}


{% block from %}
  FROM {{query.table}} main_table
{% endblock %}


{% block join %}

  {# Relationship with children tables. This tuples in children tables will also be deleted #}
  {% for relationship in query.relationships if relationship["attributes"]["type"]=="OneToMany" %}
    LEFT OUTER JOIN {{relationship["table"]}} AS {{relationship["name"]}} ON (
    {% for src_attr,dest_attr in relationship["attributes"]["condition"] %}
      {{relationship["name"]}}.{{dest_attr}} = main_table.{{src_attr}}
      {% if not loop.last %}AND{% endif %}
    {% endfor %}
  )
  {% endfor %}
  
  {# Many-to-many relationship with nexus tables. These tables will also be deleted. #}
  {% for relationship in query.relationships if relationship["attributes"]["type"]=="ManyToMany" %}
      {% include "select/join_with_many_to_many.twig.sql" %}
  {% endfor %}
  
{% endblock %}


{% block where %}
  {{ parent() }}
{% endblock where %}


{# Empty blocks #}
{% block group_by %}
{% endblock group_by %}

{% block order %}
{% endblock order %}

{% block limit %}
{% endblock limit %}

{% block for_update %}
{% endblock for_update %}

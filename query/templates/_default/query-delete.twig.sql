{% extends "query.twig.sql" %}


{% block select %}
  DELETE main_table.*
  
  {# Incluimos los alias de todas las tablas hijas que tengan como ForeignKey a este modelo (y que tengan eliminación en cascada) #}
  {% for relationship in query.relationships if relationship["attributes"]["type"]=="OneToMany" %}
    ,
    {% for src_attr,dest_attr in relationship["attributes"]["condition"] %}
      {{relationship["name"]}}
    {% endfor %}
  {% endfor %}
  
  {# Incluimos todas las tablas nexo de relaciones ManyToMany que tengan como extremo a este modelo (y que tengan eliminación en cascada) #}
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

  {# Relación con las tablas hijas. Estas tuplas de tablas hijas se eliminarán también #}
  {% for relationship in query.relationships if relationship["attributes"]["type"]=="OneToMany" %}
    LEFT OUTER JOIN {{relationship["table"]}} AS {{relationship["name"]}} ON (
    {% for src_attr,dest_attr in relationship["attributes"]["condition"] %}
      {{relationship["name"]}}.{{dest_attr}} = main_table.{{src_attr}}
      {% if not loop.last %}AND{% endif %}
    {% endfor %}
  )
  {% endfor %}
  
  {# Relación con las tablas nexo de relaciones ManyToMany. Estas tuplas e tablas hijas se eliminarán también #}
  {% for relationship in query.relationships if relationship["attributes"]["type"]=="ManyToMany" %}
      {% include "join_with_many_to_many.twig.sql" %}
  {% endfor %}
  
{% endblock %}


{% block where %}
  {{ parent() }}
{% endblock where %}


{# Bloques vacíos #}
{% block group_by %}
{% endblock group_by %}

{% block order %}
{% endblock order %}

{% block limit %}
{% endblock limit %}

{% block for_update %}
{% endblock for_update %}

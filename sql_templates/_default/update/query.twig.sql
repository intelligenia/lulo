{% extends "select/query.twig.sql" %}


{% block select %}
  UPDATE {{query.table}} main_table
{% endblock select %}


{% block from %}
{% endblock %}


{% block join %}
  {{ parent() }}

  SET
  {% for field, value in fieldsToUpdate %}
    {{field}}={{value|raw}}{% if not loop.last %}, {% endif %}
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
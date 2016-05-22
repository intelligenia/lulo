{# Campos select de una sentencia SELECT #}
{% macro selected_fields(query, prefix) %}
    {% if query.distinct %}DISTINCT{% endif %}
    {% if not query.selected_fields or query.selected_fields == null or query.selected_fields|length == 0 %}
      {{prefix}}.*
  {% else %}
    {% if not "id" in query.selected_fields %}
      {{prefix}}.id,
    {% endif %}
    {% for selected_field in query.selected_fields %}{{prefix}}.{{selected_field}}{% if not loop.last %}, {% endif %}{% endfor %}
  {% endif %}
{% endmacro %}

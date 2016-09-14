{# Select fields in SELECT query #}
{% macro selected_fields(query, prefix) %}
    {% import "escaping/escape_field.twig.sql" as escape_field %}

    {% if query.distinct %}DISTINCT{% endif %}
    {% if not query.selected_fields or query.selected_fields == null or query.selected_fields|length == 0 %}
      {{prefix}}.*
  {% else %}
    {% if not "id" in query.selected_fields %}
      {{prefix}}.id,
    {% endif %}
    {% for selected_field in query.selected_fields %}
      {% set escaped_field = escape_field.escape(selected_field) %}
      {{prefix}}.{{escaped_field}}{% if not loop.last %}, {% endif %}
    {% endfor %}
  {% endif %}
{% endmacro %}

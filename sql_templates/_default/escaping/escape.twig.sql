{% macro field(field_name) %}
    {{field_name}}
{% endmacro %}

{% macro table(database_name, table_name) %}
    {{database_name}}.{{table_name}}
{% endmacro %}

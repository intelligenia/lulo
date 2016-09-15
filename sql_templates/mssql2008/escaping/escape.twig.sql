{% macro field(field_name) %}
    [{{field_name}}]
{% endmacro %}

{% macro table(table_name) %}
    [dbo].[{{table_name}}]
{% endmacro %}

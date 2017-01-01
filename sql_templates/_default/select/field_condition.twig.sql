{% import "escaping/escape.twig.sql" as escape %}
{# Table is not escaped because it is a table alias and not a table name #}
{{table}}.{{escape.field(field)}} {{sql_operator}} {{value|raw}}
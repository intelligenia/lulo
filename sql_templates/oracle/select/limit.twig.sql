{# Oracle way of applying a LIMIT to a SELECT query (since 12.1 release) #}
{% if query.limit %}
    OFFSET {{query.limit[0]}} ROWS FETCH NEXT query.limit[1] ROWS ONLY;
{% endif %}
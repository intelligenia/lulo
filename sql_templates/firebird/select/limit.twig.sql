{# Firebird way of applying a LIMIT to a SELECT query (since 2.1 release) #}
{% if query.limit %}
    ROWS {{query.limit[0]}} TO {{query.limit[0] + query.limit[1]}}
{% endif %}
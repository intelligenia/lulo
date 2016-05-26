{% if query.limit %}
    LIMIT {{query.limit[1]}} OFFSET {{query.limit[0]}}
{% endif %}
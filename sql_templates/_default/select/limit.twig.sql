{# Almost standard way of applying a LIMIT to a SELECT query #}
{# According to Wikipedia (https://en.wikipedia.org/wiki/Select_(SQL)#Result_limits) this syntax is used by: #}
{# Netezza, MySQL, Sybase SQL Anywhere, PostgreSQL, SQLite, HSQLDB, H2, Vertica and Polyhedra #}
{% if query.limit %}
    LIMIT {{query.limit[1]}} OFFSET {{query.limit[0]}}
{% endif %}
{# Generate the CREATE TABLE DDL statement for a table #}

{# Sets type for attribute #}
{% macro attr_type(attribute_properties) %}
    {% if attribute_properties["type"] == "integer" or attribute_properties["type"] == "int" %}
        INTEGER
    {% elseif attribute_properties["type"] == "string" %}
        {% if attribute_properties["subtype"] == "date" %}        
            DATE
        {% elseif attribute_properties["subtype"] == "datetime" %}        
            DATETIME
        {% else %}
            {% if attribute_properties["max_length"] %}
                {% include "create/types/varchar.twig.sql" with {'length': attribute_properties["max_length"]}  %}
            {% else %}
                {% include "create/types/longtext.twig.sql" %}
            {% endif %}
        {% endif %}
    {% elseif attribute_properties["type"] == "blob" %}
      {% include "create/types/longblob.twig.sql" %}
    {% endif %}
{% endmacro %}


{# Sets nullability for attribute #}
{% macro attr_nullable(attribute_properties) %}
    {% if attribute_properties["null"] %}
        NULL
    {% else %}
        NOT NULL
    {% endif %}
{% endmacro %}

{# Sets autoincrementability for attribute #}
{% macro attr_autoincrementable(attribute_name, id_attribute_name) %}
    {% if attribute_name == id_attribute_name %}
        {% include "create/autoincrement_statement.twig.sql" %}
    {% else %}
    {% endif %}
{% endmacro %}

{# Sets default value for attribute #}
{% macro attr_default(attribute_properties) %}
    {% if attribute_properties["default"] %}
        DEFAULT attribute_properties["default"]
    {% endif %}
{% endmacro %}

{% import _self as this %}

{% if model %}
    CREATE TABLE {{table}}(
    {% for attribute_name, attribute_properties in attributes %}
        {{attribute_name}} {{this.attr_type(attribute_properties)}} {{this.attr_nullable(attribute_properties)}} {{this.attr_autoincrementable(attribute_name, id_attribute_name)}},
    {% endfor %}
     PRIMARY KEY ({% for attr_pk in primary_key %}{{attr_pk}}{% if not loop.last %}, {% endif %}{% endfor %})
    )
{% else %}
    CREATE TABLE {{table}}(
    {% for attribute_name, attribute_properties in attributes %}
        {{attribute_name}} {{this.attr_type(attribute_properties)}} {{this.attr_nullable(attribute_properties)}}{% if not loop.last or (loop.last and unique)%},{% endif %}
    {% endfor %}
    {% if unique %}
        PRIMARY KEY ({% for attribute_name, attribute_properties in attributes %}{{attribute_name}}{% if not loop.last %}, {% endif %}{% endfor %})
    {% endif %}
    )
{% endif %}
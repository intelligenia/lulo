
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
                VARCHAR({{attribute_properties["max_length"]}})
            {% else %}
                LONGTEXT
            {% endif %}
        {% endif %}
    {% elseif attribute_properties["type"] == "blob" %}
    LONGBLOB,
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


{# Sets default value for attribute #}
{% macro attr_default(attribute_properties) %}
    {% if attribute_properties["default"] %}
        DEFAULT attribute_properties["default"]
    {% endif %}
{% endmacro %}

{% import _self as this %}


CREATE TABLE {{table}}(
{% for attribute_name, attribute_properties in attributes %}
    {{attribute_name}} {{this.attr_type(attribute_properties)}} {{this.attr_nullable(attribute_properties)}},
{% endfor %}
 PRIMARY KEY ({% for attr_pk in primary_key %}{{attr_pk}}{% if not loop.last %}, {% endif %}{% endfor %})
)

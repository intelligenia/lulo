{% import "select/selected_fields.twig.sql" as q %}

{# Start transaction #}
{% block start_transaction %}
	{% if query.is_transaction %}
		START TRANSACTION
	{% endif %}
{% endblock start_transaction %}

SELECT * FROM ( 

    {% block select %}
            SELECT
            {% block selected_fields %}

                    {# Standard fields #}
                    {% if query.selected_fields and query.selected_fields|length > 0 %}
                            {{q.selected_fields(query, query.table_alias)}}{% if query.aggregations|length > 0 %},{% endif %}
                    {% elseif query.aggregations|length > 0 %}
                            {# If there were no fields in the query is an aggregation query #}
                    {% endif %}		

                    {# Agregaciones #}
                    {% if query.aggregations|length > 0 %}
                            {% for aggregation in query.aggregations %}
                                    {{aggregation.functionName}}(
                                            {% if aggregation.fields and aggregation.fields|length > 0 %}
                                                    {% for field in aggregation.fields %}{{query.table_alias}}.{{field}}{% if not loop.last %}, {% endif %}{% endfor %}
                                            {% else %}
                                                    *
                                            {% endif %}
                                    ) AS {{aggregation.alias}}
                            {% endfor %}
                    {% endif %}
                , ROW_NUMBER() OVER (ORDER BY {{query.model_id_attribute_name}}) as _row
            {% endblock selected_fields %}
    {% endblock select %}

    {% block from %}
    FROM {{query.table}} AS {{query.table_alias}}
    {% endblock %}

    {% block join %}
    {# BEGIN RELACIONES #}
    {# Foreign-key relationships #}
    {% for relationship in query.relationships if relationship["attributes"]["type"]=="ForeignKey" %}
            {% include "select/join_with_foreign_key.twig.sql" %}
    {% endfor %}

    {# Many-to-many relationships #}
    {% for relationship in query.relationships if relationship["attributes"]["type"]=="ManyToMany" %}
                    {% include "select/join_with_many_to_many.twig.sql" %}
    {% endfor %}
    {# END RELACIONES #}
    {% endblock join %}

    {% block where %}
    {# Conditions #}
    {% if query.filters %}
    WHERE (
            {% for filter in query.filters %}
                    (
                            {% set firstIteration = loop.first %}
                            {% set isPositive = ( filter["type"] == "positive" ) %}
                            {% for conditionGroups in filter["conditionGroups"] %}
                                    {% if not loop.first %}OR{% endif %}

                                    {# Condiciones #}
                                    {% for model_i,conditions in conditionGroups.conditionsByModel %}
                                            {% if conditions|length > 0 %}
                                                                    {% if not isPositive %}NOT{% endif %}(
                                                                            {% for condition in conditions %}
                                                                                    {{condition.sql()|raw}}
                                                                                    {% if not loop.last %} AND{% endif %}
                                                                            {% endfor %}
                                                                    ) {% if not loop.last %} AND{% endif %}
                                            {% endif %}
                                    {% endfor %}

                            {% endfor %}
                    )
                    {% if not loop.last %}AND{% endif %} 
            {% endfor %}
    )
    {% endif %}
    {% endblock where %}

    {# Group by clause #}
    {% block group_by %}
      {% if query.has_group_by %}
        GROUP BY 
        {% for aggregation in query.aggregations %}
          {% for field in aggregation.fields %}{{query.table_alias}}.{{field}}{% if not loop.last %}, {% endif %}{% endfor %}
        {% endfor %}
      {% endif %}
    {% endblock group_by %}

    {# Order of the results #}
    {% block order %}
            {% if query.order %}
                    ORDER BY {% for fieldOrder in query.order %}{{fieldOrder.table_alias}}.{{fieldOrder.field}} {{fieldOrder.order_value}}{% if not loop.last %}, {% endif %}{% endfor %}
            {% endif %}
    {% endblock order %}
) _superquery
WHERE _superquery._row > {{query.limit[0]}} and _superquery._row <= {{query.limit[1]}}

{# For SELECT FOR UPDATE queries #}
{% block for_update %}
	{% if query.for_update %}
		FOR UPDATE
	{% endif %}
{% endblock for_update %}

{# Close transaction #}
{% block commit %}
	{% if query.is_transaction %}
		COMMIT
	{% endif %}
{% endblock commit %}

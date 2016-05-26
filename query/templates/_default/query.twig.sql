{% import "selected_fields.twig.sql" as q %}

{# Inicio de transacción #}
{% block start_transaction %}
	{% if query.is_transaction %}
		START TRANSACTION
	{% endif %}
{% endblock start_transaction %}


{% block select %}
	SELECT
	{% block selected_fields %}
		
		{# Campos normales #}
		{% if query.selected_fields and query.selected_fields|length > 0 %}
			{{q.selected_fields(query, "main_table")}}{% if query.aggregations|length > 0 %},{% endif %}
		{% elseif query.aggregations|length > 0 %}
			{# Si no se le han pasado campos, es una consulta de agregación #}
		{% endif %}		
		
		{# Agregaciones #}
		{% if query.aggregations|length > 0 %}
			{% for aggregation in query.aggregations %}
				{{aggregation.functionName}}(
					{% if aggregation.fields and aggregation.fields|length > 0 %}
						{% for field in aggregation.fields %}main_table.{{field}}{% if not loop.last %}, {% endif %}{% endfor %}
					{% else %}
						*
					{% endif %}
				) AS {{aggregation.alias}}
			{% endfor %}
		{% endif %}
		
	{% endblock selected_fields %}
{% endblock select %}

{% block from %}
FROM {{query.table}} AS main_table
{% endblock %}

{% block join %}
{# BEGIN RELACIONES #}
{# Relaciones de tipo clave externa (a uno) #}
{% for relationship in query.relationships if relationship["attributes"]["type"]=="ForeignKey" %}
	{% include "lulo_query/join_with_foreign_key.twig.sql" %}
{% endfor %}

{# Relaciones de tipo clave Muchos a Muchos #}
{% for relationship in query.relationships if relationship["attributes"]["type"]=="ManyToMany" %}
		{% include "lulo_query/join_with_many_to_many.twig.sql" %}
{% endfor %}
{# END RELACIONES #}
{% endblock join %}

{% block where %}
{# Condiciones #}
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

{# Agrupación por campos #}
{% block group_by %}
  {% if query.has_group_by %}
    GROUP BY 
    {% for aggregation in query.aggregations %}
      {% for field in aggregation.fields %}main_table.{{field}}{% if not loop.last %}, {% endif %}{% endfor %}
    {% endfor %}
  {% endif %}
{% endblock group_by %}

{# Orden de los resultados #}
{% block order %}
	{% if query.order %}
			ORDER BY {% for fieldOrder in query.order %}{{fieldOrder.tableAlias}}.{{fieldOrder.field}} {{fieldOrder.orderValue}}{% if not loop.last %}, {% endif %}{% endfor %}
	{% endif %}
{% endblock order %}

{# Límite de los resultados obtenidos #}
{% block limit %}
    {% include "limit_select.twig.sql" %}
{% endblock limit %}

{# Para tener consultas SELECT FOR UPDATE #}
{% block for_update %}
	{% if query.for_update %}
		FOR UPDATE
	{% endif %}
{% endblock for_update %}

{# Cierre de transacción #}
{% block commit %}
	{% if query.is_transaction %}
		COMMIT
	{% endif %}
{% endblock commit %}

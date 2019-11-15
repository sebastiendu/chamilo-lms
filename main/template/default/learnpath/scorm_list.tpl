{% if data_list is not empty %}
<div id="learning_path_toc" class="scorm-list">
    <div class="scorm-title">
        <h4>{{ lp_title_scorm }}</h4>
    </div>
    <div class="scorm-body">
        <div id="inner_lp_toc" class="inner_lp_toc scrollbar-light">
            {% for item in data_list %}
            <div id="toc_{{ item.id }}" class="{{ item.class }} item-{{ item.type }}">
                {% if item.type == 'dir' %}
                <div class="section {{ item.css_level }}" title="{{ item.description | striptags | e('html') }}">
                    {{ item.title }}
                </div>
                {% else %}
                <div class="item {{ item.css_level }}" title="{{ item.description | striptags | e('html') }}">
                    <a name="atoc_{{ item.id }}"></a>
                    <a data-type="type-{{ item.type }}" class="items-list"  href="#"
                       onclick="switch_item('{{ item.current_id }}','{{ item.id }}'); return false;">
                        {{ item.title }}
                    </a>
                </div>
                {% endif %}
            </div>
            {% endfor %}
        </div>
    </div>
</div>
{% endif %}

{% if data_panel is not empty %}

<div id="learning_path_toc" class="scorm-collapse">
    <div class="scorm-title">
        <h4>
             {{ lp_title_scorm }}
        </h4>
    </div>
    <div class="panel-group" role="tablist" aria-multiselectable="true">
        <ul class="scorm-collapse-list">
        {{ dump(data_panel) }}
        </ul>
    </div>
</div>
{% endif %}
{% for item in data %}
    {% if item.type == 'dir' %}
        <li class="type-dir">
            <div class="panel panel-default {{ item.parent ? 'lower':'higher' }}" data-lp-id="{{ item.id }}"
                    {{ item.parent ? 'data-lp-parent="' ~ item.parent ~ '"' : '' }}>
                <div class="status-heading">
                    <div class="panel-heading" role="tab" id="heading-{{ item.id }}">
                        <h4>
                            <a class="item-header" role="button" data-toggle="collapse"
                               data-parent="#scorm-panel{{ item.parent ? '-' ~ item.parent : '' }}"
                               href="#collapse-{{ item.id }}" aria-expanded="true"
                               aria-controls="collapse-{{ item.id }}">
                                {{ item.title }}
                            </a>
                        </h4>
                    </div>
                </div>
                <div id="collapse-{{ item.id }}" class="panel-collapse collapse {{ item.parent_current }}"
                     role="tabpanel" aria-labelledby="heading-{{ item.id }}">
                    <div class="panel-body">
                        {% for item.children in row %}
                            sdsdsd
                            {% endfor %}
                    </div>
                </div>
            </div>
        </li>
    {% else %}
        <li id="toc_{{ item.id }}" class="{{ item.class }} item-{{ item.type }}">
            <div class="sub-item type-{{ item.type }}">
                <a name="atoc_{{ item.id }}"></a>
                <a data-type="type-{{ item.type }}" class="item-action" href="#"
                   onclick="switch_item('{{ item.current_id }}','{{ item.id }}'); return false;">
                    <i class="fa fa-chevron-circle-right" aria-hidden="true"></i>
                    {{ item.title }}
                </a>
            </div>
        </li>
    {% endif %}
{% endfor %}
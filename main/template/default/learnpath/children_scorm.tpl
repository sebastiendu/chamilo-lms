{% for item in data %}
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
{% endfor %}
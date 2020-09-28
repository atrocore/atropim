<button class="btn btn-link catalog-link" data-toggle="collapse" data-parent=".category-tree" data-target=".catalog-{{hash}}" data-id="{{catalog.id}}">
    <span class="catalog-title">{{catalog.name}}</span>
    <span class="caret"></span>
</button>
<div class="catalog-{{hash}} panel-collapse collapse" data-id="{{catalog.id}}">
    <ul class="list-group list-group-tree">
        {{#each rootCategoriesList}}
            {{{html}}}
        {{/each}}
    </ul>
</div>

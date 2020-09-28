<button type="button" class="btn btn-default collapse-panel" data-action="collapsePanel">
    <span class="toggle-icon-left fas fa-angle-left"></span>
    <span class="toggle-icon-right fas fa-angle-right hidden"></span>
</button>
<div class="category-panel">
    {{#if catalogDataList}}
    <div class="panel-group text-center">
        <div class="btn-group category-buttons">
            <button type="button" class="btn btn-default active" data-action="selectAll">{{translate 'All'}}</button>
            <button type="button" class="btn btn-default" data-action="selectWithoutCategory">{{translate 'withoutAnyCategory' category='labels' scope=scope}}</button>
        </div>
    </div>
    <div class="panel-group category-search">
        {{{categorySearch}}}
    </div>
    <div class="panel-group category-tree">
        {{#each catalogDataList}}
        <div class="panel" data-name="{{name}}">
            {{{var key ../this}}}
        </div>
        {{/each}}
    </div>
    {{else}}
    <div class="no-data">{{translate 'No Data'}}</div>
    {{/if}}
</div>
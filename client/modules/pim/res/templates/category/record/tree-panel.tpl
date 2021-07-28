<button type="button" class="btn btn-default collapse-panel" data-action="collapsePanel">
    <span class="toggle-icon-left fas fa-angle-left"></span>
    <span class="toggle-icon-right fas fa-angle-right hidden"></span>
</button>
<div class="category-panel">
    <div class="panel-group text-center">
        <div class="btn-group category-buttons">
            <button type="button" class="btn btn-default active" data-action="selectAll">{{translate 'All'}}</button>
        </div>
    </div>
    <div class="panel-group category-search">
        {{{categorySearch}}}
    </div>
    <div class="panel-group category-tree"></div>
</div>
{{#if topBar}}
<div class="list-buttons-container">
    {{#if paginationTop}}
    <div>
        {{{pagination}}}
    </div>
    {{/if}}

    {{#if checkboxes}}
    <div class="check-all-container" data-name="r-checkbox">
        <span class="select-all-container"><input type="checkbox" class="select-all" {{#unless collection.models.length}}disabled{{/unless}}></span>
    </div>

    {{#if massActionList}}
        <div class="btn-group actions">
            <button type="button" class="btn btn-default dropdown-toggle actions-button" data-toggle="dropdown" disabled>
                {{translate 'Actions'}} <i class="ph ph-caret-down"></i>
            </button>
            <ul class="dropdown-menu">
                {{#each massActionList}}
                {{#if action}}
                <li><a href="javascript:" data-action="{{action}}" data-id="{{id}}" class='mass-action'>{{label}}</a></li>
                {{else if divider}}
                    <li class="divider"></li>
                {{else}}
                <li><a href="javascript:" data-action="{{./this}}" class='mass-action'>{{translate this category="massActions" scope=../scope}}</a></li>
                {{/if}}
                {{/each}}
            </ul>
        </div>
    {{/if}}
    {{/if}}

    <div class="filter-container"></div>

    {{#each buttonList}}
        {{button name scope=../scope label=label style=style}}
    {{/each}}

    <div class="counters-container">
        <div class="text-muted selected-count hidden">{{translate 'Selected'}}: <span class="selected-count-span">0</span></div>

        <div class="items-in-row-container">
            <div class="items-in-row-label">{{translate 'itemsInRow' category='labels' scope=scope}}:</div>
            <div class="btn-group items-in-row">
                <button type="button" class="btn btn-default dropdown-toggle items-in-row-button" data-toggle="dropdown">
                    {{itemsInRow}}
                </button>
                <ul class="dropdown-menu dropdown-menu-right">
                    {{#each itemsInRowOptions}}
                        <li>
                            <a href="javascript:" data-action="setItemsInRow" data-name="{{this}}">{{this}}</a>
                        </li>
                    {{/each}}
                </ul>
            </div>
        </div>
        <div class="sort-container">
            <div class="sort-label"> {{translate 'sort' category='labels' scope=scope}}:</div>
            <div class="btn-group sort-field">
                <button type="button" class="btn btn-default dropdown-toggle sort-field-button" data-toggle="dropdown">
                    {{translate collection.sortBy category='fields' scope=scope}}
                </button>
                <ul class="dropdown-menu dropdown-menu-right">
                    {{#each sortFields}}
                        <li>
                            <a href="javascript:" data-action="sortByField" data-name="{{this}}">{{translate this category='fields' scope=../scope}}</a>
                        </li>
                    {{/each}}
                </ul>
            </div>
            <div class="btn-group sort-direction">
                <button type="button" class="btn btn-default sort-direction-button" data-action="sortByDirection">
                    {{#if collection.asc}}
                        &#8593;
                    {{else}}
                        &#8595;
                    {{/if}}
                </button>
            </div>
        </div>

        {{#if displayTotalCount}}
            <div class="text-muted total-count">
                {{translate 'Total'}}: <span class="total-count-span">{{collection.total}}</span>
            </div>
        {{/if}}
    </div>
</div>
{{/if}}

{{#if collection.models.length}}
<div class="list">
	<div>
		<div class="col-xs-12 plate">
			<div class="row">
				{{#each rowList}}
					<div class="col-sm-12 col-xs-12 item-container" data-id="{{./this}}">
						{{{var this ../this}}}
					</div>
				{{/each}}
			</div>
		</div>
	</div>

    <div class="show-more{{#unless showMoreActive}} hide{{/unless}}">
        <a type="button" href="javascript:" class="btn btn-default btn-block" data-action="showMore" {{#if showCount}}title="{{translate 'Total'}}: {{collection.total}}"{{/if}}>
            <span class="more-label">{{countLabel}}</span>
        </a>
    </div>
</div>

{{else}}
    {{translate 'No Data'}}
{{/if}}

<style>
	.plate {
		padding: 0 15px;
	}
	.item-container {
		margin-bottom: 17px;
		position: relative;
        min-height: 1px;
        padding-left: 8px;
        padding-right: 8px;
		width: {{itemContainerWidth}}%;
	}
	.list-buttons-container {
        margin-left: 5px;
	}
	.check-all-container {
	    width: 20px;
        white-space: nowrap;
	}
	.check-all-container .select-all-container {
        line-height: 19px;
        height: 19px;
	    float: left;
	}
	.check-all-container .checkbox-dropdown {
        margin-left: 3px;
        top: -1px;
	}
	.check-all-container .checkbox-dropdown > a {
	    padding: 0;
        line-height: 1;
        color: #000;
	}
	.list-buttons-container > .sort-container,
	.list-buttons-container > .items-in-row-container {
	    line-height: 0;
	    float: right;
	    margin-right: 0;
	}
	.sort-container .sort-label,
	.items-in-row-container .items-in-row-label {
	    line-height: 19px;
	    display: inline-block;
	    vertical-align: middle;
        margin-right: 5px;
	}
    .items-in-row-container .items-in-row {
        margin-right: 20px;
    }
	.sort-container .sort-field .dropdown-menu {
        max-height: 500px;
        overflow-y: auto;
	}
	.sort-container button.btn,
	.items-in-row-container button.btn {
        color: #000;
        border: 0;
        padding: 0;
        background: #fff;
	}
	.items-in-row-container button.btn:hover,
	.items-in-row-container button.btn:focus,
	.items-in-row-container button.btn:active,
	.items-in-row-container .open > button.btn.items-in-row-button,
	.sort-container button.btn:hover,
	.sort-container button.btn:focus,
	.sort-container button.btn:active,
	.sort-container .open > button.btn.sort-field-button,
	.sort-container .open > button.btn.sort-direction-button {
	    background: #fff;
        box-shadow: none;
	}
	.total-count {
	    margin-left: 20px;
	}
</style>

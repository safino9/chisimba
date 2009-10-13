/*
 * Ext JS Library 3.0 RC2
 * Copyright(c) 2006-2009, Ext JS, LLC.
 * licensing@extjs.com
 * 
 * http://extjs.com/license
 * By Paul Mungai
 * wandopm@gmail.com
 */

Ext.onReady(function(){
   
var usercontextdata = new Ext.data.JsonStore({
        root: 'usercourses',
        totalProperty: 'contextcount',
        idProperty: 'code',
        remoteSort: false,        
        fields: ['code', 'coursecode', 'title', 'lecturertitle', 'lecturers', 'accesstitle','access' ],
        proxy: new Ext.data.HttpProxy({        	 	
            	url: usercontexturi
        }),
        listeners:{ 
    		'loadexception': function(theO, theN, response){
    			//alert(response.responseText);
    		},
    		'load': function(){
    				//alert('load');	
    			}
    	},
	});
	 usercontextdata.setDefaultSort('title', 'asc');
	 
    // pluggable renders
    function renderTitle(value, p, record){
        return String.format(
        		'<b><a href="'+baseuri+'?module=context&action=joincontext&contextcode={1}">{0}</a></b>', value, record.data.code);
    }

    var usergrid = new Ext.grid.GridPanel({
        el:'courses-grid',
        width:700,
        height:350,
        title:'My Courses',
        store: usercontextdata,
        trackMouseOver:false,
        disableSelection:true,
        loadMask: true,

        // grid columns
        columns:[
        {
            header: "Course Code",
            dataIndex: 'code',
            width: 100,            
            sortable: true
        },{
            id: 'code', // id assigned so we can apply custom css (e.g. .x-grid-col-topic b { color:#333 })
            header: "Title",
            dataIndex: 'title',
            width: 320,
            renderer: renderTitle,
            sortable: true
        },{
            header: "Lecturers",
            dataIndex: 'lecturers',
            width: 280,
            hidden: false,
            sortable: true
        }],

        // customize view config
        viewConfig: {
            forceFit:true,
            enableRowBody:true,
            showPreview:false,
            getRowClass : function(record, rowIndex, p, store){
                if(this.showPreview){
                    p.body = '<p><b>'+record.data.accesstitle+' </b></p><p>'+record.data.access+'</p>';
                    return 'x-grid3-row-expanded';
                }
                return 'x-grid3-row-collapsed';
            }
        },

        // paging bar on the bottom
        bbar: new Ext.PagingToolbar({
            pageSize: 100,
            store: usercontextdata,
            displayInfo: true,
            displayMsg: 'Displaying courses {0} - {1} of {2}',
            emptyMsg: "No courses to display",
            items:[
                '-', {
                pressed: false,
                enableToggle:true,
                text: 'Show Access Details',
                cls: 'x-btn-text-icon details',
                toggleHandler: function(btn, pressed){
                    var view = usergrid.getView();
                    view.showPreview = pressed;
                    view.refresh();
                }
            }]
        })
    });

    // render it
    usergrid.render();

    // trigger the data store load
    usercontextdata.load({params:{start:0, limit:100}});
});

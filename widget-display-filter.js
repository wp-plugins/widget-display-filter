jQuery(document).ready(function ($) {
    widget_filter_tabs();
    function widget_filter_tabs(){
        $('#widget-filter-tabs').tabs({
            active:widget_display_filter_tab,            
        });
    }
    
    WidgetFilterPostid = function(nonce, hashtag){
        var opt = widget_filter[hashtag];
        var sel = opt['in_postid'];
        var pid = opt['postid'];
        $("input[name='widget_display_filter[in_postid]']").val([sel]);
        var csvpid = '';
        for (var i=0; i<pid.length; i++) {
            csvpid += pid[i] + ",";
        }
        csvpid = csvpid.slice(0,-1);
        $('#filter-postid').val(csvpid);

        $( "#postid-dialog" ).dialog({
            dialogClass : 'wp-dialog',
            modal       : true,
            autoOpen    : true,
            resizable   : false,
            draggable   : false,
            height      : 480,
            width       : 440,
            buttons     : {  
                "O K": function() {
                    var in_pid = $("input[name='widget_display_filter[in_postid]']:checked").val();
                    var csvpid = $('#filter-postid').val();
                    $.ajax({ 
                        type: 'POST', 
                        url: ajaxurl,
                        data: {
                            action: "Widget_filter_postid", 
                            hashtag: hashtag, 
                            _ajax_nonce: nonce, 
                            in_postid: in_pid, 
                            postid: csvpid,
                        }, 
                        dataType: 'json',
                        success: function(response, dataType) {
                            if(response.success){
                                //current postid data update
                                $('#widget-filter-postid-' + hashtag).html(response.data);
                                widget_filter[hashtag]['in_postid'] = $("input[name='widget_display_filter[in_postid]']:checked").val()
                                csvpid = $("#widget-filter-postid-" + hashtag + " > span").text();
                                var arpid = new Array();
                                arpid = csvpid.split(",");
                                widget_filter[hashtag]['postid'] = arpid;
                            }
                            else {
                                alert('Post ID Error : ' + response.data);
                            }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown){
                            //alert('Error : ' + errorThrown);
                        }
                    });
                    $('#postid-dialog').dialog( "close" );
                },
                "Cancel": function() {  
                    $('#postid-dialog').dialog('close');  
                }  
            },  
            close: function() {
            }
        });
    };
    
    WidgetFilterCategory = function(nonce, hashtag){
        var opt = widget_filter[hashtag];
        var sel = opt['in_category'];
        var cat = opt['category'];
        $("input[name='widget_display_filter[in_category]']").val([sel]);
        $("input[name='post_category[]']").prop('checked',false);
        for (var i=0; i<cat.length; i++) {
            var idtag = '#in-category-' + cat[i];
            $(idtag).prop('checked',true);
        }

        $( "#category-dialog" ).dialog({
            dialogClass : 'wp-dialog',
            modal       : true,
            autoOpen    : true,
            resizable   : false,
            draggable   : false,
            height      : 480,
            width       : 440,
            buttons     : {  
                "O K": function() {
                    var in_cat = $("input[name='widget_display_filter[in_category]']:checked").val();
                    var csvcat = $('.categorychecklist li input:checked').map(function(){ return $(this).attr("value"); }).get().join(',');
                    $.ajax({ 
                        type: 'POST', 
                        url: ajaxurl,
                        data: {
                            action: "Widget_filter_category", 
                            hashtag: hashtag, 
                            _ajax_nonce: nonce, 
                            in_category: in_cat, 
                            category: csvcat,
                        }, 
                        dataType: 'json',
                        success: function(response, dataType) { 
                            if(response.success){
                                //current category data update
                                $('#widget-filter-category-' + hashtag).html(response.data); 
                                widget_filter[hashtag]['in_category'] = in_cat;
                                var arcat = new Array();
                                arcat = csvcat.split(",");
                                widget_filter[hashtag]['category'] = arcat;
                            }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown){
                            //alert('Error : ' + errorThrown);
                        }
                    });
                    $('#category-dialog').dialog( "close" );
                },
                "Cancel": function() {  
                    $('#category-dialog').dialog('close');  
                }  
            },  
            close: function() {
            }
        });
    };

    WidgetFilterPosttag = function(nonce, hashtag){
        var opt = widget_filter[hashtag];
        var sel = opt['in_post_tag'];
        var ptag = opt['post_tag'];
        $("input[name='widget_display_filter[in_post_tag]']").val([sel]);
        $("input[name='post_post_tag[]']").prop('checked',false);
        for (var i=0; i<ptag.length; i++) {
            var idtag = '#in-post_tag-' + ptag[i];
            $(idtag).prop('checked',true);
        }

        $( "#posttag-dialog" ).dialog({
            dialogClass : 'wp-dialog',
            modal       : true,
            autoOpen    : true,
            resizable   : false,
            draggable   : false,
            height      : 480,
            width       : 440,
            buttons     : {  
                "O K": function() {
                    var in_ptag = $("input[name='widget_display_filter[in_post_tag]']:checked").val();
                    var csvptag = $('.posttagchecklist li input:checked').map(function(){ return $(this).attr("value"); }).get().join(',');
                    $.ajax({ 
                        type: 'POST', 
                        url: ajaxurl,
                        data: {
                            action: "Widget_filter_post_tag", 
                            hashtag: hashtag, 
                            _ajax_nonce: nonce, 
                            in_post_tag: in_ptag, 
                            post_tag: csvptag,
                        }, 
                        dataType: 'json',
                        success: function(response, dataType) { 
                            if(response.success){
                                //current tag data update
                                $('#widget-filter-posttag-' + hashtag).html(response.data); 
                                widget_filter[hashtag]['in_post_tag'] = in_ptag;
                                var artag = new Array();
                                artag = csvptag.split(",");
                                widget_filter[hashtag]['post_tag'] = artag;
                            }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown){
                            //alert('Error : ' + errorThrown);
                        }
                    });
                    $('#posttag-dialog').dialog( "close" );
                },
                "Cancel": function() {  
                    $('#posttag-dialog').dialog('close');  
                }  
            },  
            close: function() {
            }
        });
    };
});    



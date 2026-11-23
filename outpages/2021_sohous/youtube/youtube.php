
$(function() {
    var el = "";
    $.ajax({
        type: "GET",
        url: "https://www.googleapis.com/youtube/v3/search",
        data: {
            order:"date",
            part:"snippet",
            channelId:"PLXLxTt8xUmeqb9mtsINxSWSyB1iN1yeGC",
            key:"",
            maxResults:10
        },
        success: function(data){
            $.each(data.items, function(index, item) {
                el  = '<div class="">';
                el += '<iframe width="750" height="422" src="https://www.youtube.com/embed/' + item.id.videoId + '" frameborder="0" allowfullscreen></iframe>';
                el += '<h2>' + item.snippet.title + '</h2>';
                el += '</div>';
                $("body").append(el);
            });
        },
        error: function(response){
            console.log("Request Failed");
        }
    });

});

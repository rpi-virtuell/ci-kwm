jQuery.ajax({
    type: 'POST',
    url: voting_ajax.ajaxurl,
    data: {
        action: 'serversidefunction',
        title: voting_ajax.title
    },
    success: function (data, textStatus, XMLHttpRequest) {
        alert(data);
    },
    error: function (XMLHttpRequest, textStatus, errorThrown) {
        alert(errorThrown);
    }
});

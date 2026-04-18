(function () {
    'use strict';

    var config = window.polskiQa || {};

    function vote(commentId) {
        var formData = new FormData();
        formData.append('action', 'polski_qa_vote');
        formData.append('comment_id', commentId);
        formData.append('nonce', config.nonce || '');

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data && data.success) {
                    window.location.reload();
                }
            });
    }

    document.addEventListener('click', function (event) {
        var target = event.target.closest('[data-polski-qa-vote]');
        if (!target) {
            return;
        }
        event.preventDefault();
        var commentId = target.getAttribute('data-polski-qa-vote');
        if (commentId) {
            vote(commentId);
        }
    });
})();

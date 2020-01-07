$(document).ready(function () {
    var isUpdating = false;
    var $updateBtn = $('.geomate-utility [data-geomate-update-btn]');
    var $updateStatus = $('.geomate-utility [data-geomate-update-status]');
    var $updateSpinner = $('.geomate-utility [data-geomate-update-spinner]');
    var $errorHolder = $('.geomate-utility .geomate-utility__error');


    function updateErrorHolder() {
        $errorHolder.removeClass('geomate-utility__error').removeClass('error').addClass('geomate-utility__success').addClass('success');
        $errorHolder.text('Database updated just now!');
    }

    function updateDatabase(url) {
        if (!isUpdating && url !== '') {
            isUpdating = true;

            $updateBtn.addClass('disabled');
            $updateSpinner.removeClass('invisible');
            $updateStatus.text('Updating...');

            var jqxhr = $.get(url).done(function (result) {
                if (result && result.success) {
                    $updateStatus.text('Database updated successfully');
    
                    if ($errorHolder.length > 0) {
                        updateErrorHolder();
                    }
                } else {
                    $updateStatus.text('An error occurred! Please check your settings and try again.');
                }
            }).fail(function () {
                $updateStatus.text('An error occurred! Please check your settings and try again.');
            }).always(function () {
                $updateBtn.removeClass('disabled');
                $updateSpinner.addClass('invisible');
                isUpdating = false;
            });
        }
    }

    if ($updateBtn.length > 0 && $updateBtn.data('inited') !== true) {
        $updateBtn.on('click', function (e) {
            e.preventDefault();
            updateDatabase($(this).data('update-url'));
        });

        $updateBtn.data('inited', true);
    }
});

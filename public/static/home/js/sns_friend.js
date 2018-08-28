$(function() {
    //加关注
    $("[ds_type='followbtn']").on('click', function() {
        var data_str = $(this).attr('data-param');
        eval("data_str = " + data_str);
        $.getJSON(HOMESITEURL + '/Membersnsfriend/addfollow?mid=' + data_str.mid, function(data) {
            if (data) {
                var obj = $('#recordone_' + data_str.mid);
                obj.find('[ds_type="signmodule"]').children().hide();
                if (data.state == 2) {
                    obj.find('[ds_type=\"mutualsign\"]').show();
                } else {
                    obj.find('[ds_type=\"followsign\"]').show();
                }
                showSucc('关注成功');
            } else {
                showError('关注失败');
            }
        });
        return false;
    });
    //取消关注
    $("[ds_type='cancelbtn']").on('click', function() {
        var data_str = $(this).attr('data-param');
        eval("data_str = " + data_str);
        $.getJSON(HOMESITEURL + '/Membersnsfriend/delfollow?mid=' + data_str.mid, function(data) {
            if (data) {
                $('#recordone_' + data_str.mid).hide();
                showSucc('取消成功');
            } else {
                showError('取消失败');
            }
        });
        return false;
    });
    // 关注
    $('*[dstype="batchFollow"]').on('click', function() {
        eval("data_str = " + $(this).attr('data-param'));
        ajax_get_confirm('', HOMESITEURL + '/Membersnsfriend/batch_addfollow?ids=' + data_str.ids);
    });
});
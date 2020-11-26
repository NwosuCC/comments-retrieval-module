// ===================================================================
// From JS Library
// -------------------------------------------------------------------
function isEmpty(varr) {
    var check_1 = (varr === '' || varr === 0 || varr === false);
    var check_2 = (varr === null || varr === undefined);
    return check_1 || check_2;
}
// ===================================================================

var handler, n, trackId, poll, pollInterval, pollStart, pollDuration,
    running = false, progress = null, progressBar, done, status, link, count,
    fixedLength, currentWidth, frameInterval,
    error = null;

function resetVars() {
    handler = 'index.php'; n = 0; trackId = ''; running = false;
    poll = null; pollInterval = 10000; pollStart = 0; pollDuration = 0;
    progressBar = document.getElementById("pgBar");
    done = false; status = 0; link = ''; count = 0;
    fixedLength = 0; currentWidth = 0; frameInterval = 100;
}


$(document).ready(function (e){
    Alert.create();
    CheckboxGroup.create();
    sampleURLs.create();

    $(document)
        .on({
            click: function (e) {
                submitForm();
                console.log("Handler for .submit() called.");
            }
        }, '#btn');

});

function toJSON(result) {
    try{
        result = JSON.parse(result);
    } catch (e){
        console.log('JSON Parse Error: ' + JSON.stringify(e.message));
        return;
    }
    return result;
}

function submitForm(){
    if(running){
        Alert.alert('info', 'Data retrieval is already in progress');
        return;
    }

    var form = {};  resetVars();  resetProgressBar();

    $('#form').find('input, textarea').each(function (i, e) {
        var name = e.name, id = e.id, type = e.type;
        var value = '', el = $('#'+id), className = el.attr('class');
        switch(type){
            case 'radio': {
                if(name === 'delivery'){
                    if(!el.prop('checked')){ return; }
                    value = el.val();
                }
            } break;
            case 'checkbox': {
                if(className === 'f_'){
                    if($('#f_all').prop('checked') && form.f_all){ return; }
                    value = el.prop('checked') ? 1 : 0;
                }
            } break;
            case 'textarea': case 'email': case 'text': {
                value = el.val();
            } break;
        }
        form[name] = value;
    });

    form.url = ' https://itunes.apple.com/us/app/grubhub-food-delivery-takeout/id302920553?mt=8';
    form.delivery = 2;  form.name = 'AB Cyndy';  form.email = 'cyndy23a@gmail.com';

    running = true;
    // alert('form: '+JSON.stringify(form));

    $.ajax({
        // async: true,
        method: 'POST',
        // dataType: 'json',
        url: handler,
        data: form,
        success: function(result){
            alert('result_form_success: '+JSON.stringify(result));
            result = toJSON(result);
            if(isEmpty(result) || (result !== null && result.error)){
                if(result !== null && result.error){
                    Alert.alert('error', result.error);
                }
                document.getElementById("btn").disabled = running = false;
                return null;

            }else{
                if(result.message.id){
                    trackId = result.message.id;
                    Alert.alert('success', 'Data retrieval in progress');

                    setButtonStates();

                    setTimeout(function(){ pollServer(); }, 100);
                }
            }
        },
        error: function(result) {
            result = 'result_form_error: '+JSON.stringify(result);
            alert(result);
            console.log(result);
        }
    });

    /*$.post(handler, form, function(result){
        alert('result 1: '+JSON.stringify(result));
        result = toJSON(result);

        if(isEmpty(result) || (result !== null && result.error)){
            if(result !== null && result.error){
                Alert.alert('error', result.error);
            }
            document.getElementById("btn").disabled = running = false;
            return null;

        }else{
            if(result.message.id){
                trackId = result.message.id;
                Alert.alert('success', 'Data retrieval in progress');

                setButtonStates();

                setTimeout(function(){ pollServer(); }, 100);
            }
        }
    });*/
}

function pollServer(){
    var params = { tp:trackId };
    pollStart = Date.now();

    $.ajax({
        // async: true,
        method: 'GET',
        // dataType: 'json',
        url: handler,
        data: params,
        success: function(result){
            alert('result_poll_success: '+JSON.stringify(result));
            result = toJSON(result);
            if(!pollDuration){
                pollDuration = Date.now() - pollStart;
                pollInterval += pollDuration;
            }

            if(!isEmpty(result.message) && !isEmpty(result.message.status)){
                var message = '', mailed;
                status = result.message.status;  link = result.message.link;
                count = result.message.count;    mailed = result.message.mailed;

                if(!isEmpty(link)){
                    done = true;  pollInterval = 50;  frameInterval = 10;
                    if(!fixedLength){ fixedLength = status - currentWidth; }

                    if(mailed === true){
                        message = 'CSV file has been sent to your email';
                    }else{
                        message = 'CSV file download will begin shortly...';
                        setTimeout(function () { window.location.href = link; }, 1000);
                    }
                    Alert.alert('success', message);
                }
            }
        },
        error: function(result) {
            result = 'result_poll_error: '+JSON.stringify(result);
            alert(result);
            console.log(result);
        }
    });

    /*$.get(handler, params).done(function(result){
        alert('result poll: '+JSON.stringify(result));
        result = toJSON(result);
        if(!pollDuration){
            pollDuration = Date.now() - pollStart;
            pollInterval += pollDuration;
        }

        if(!isEmpty(result.message) && !isEmpty(result.message.status)){
            var message = '', mailed;
            status = result.message.status;  link = result.message.link;
            count = result.message.count;    mailed = result.message.mailed;

            if(!isEmpty(link)){
                done = true;  pollInterval = 50;  frameInterval = 10;
                if(!fixedLength){ fixedLength = status - currentWidth; }

                if(mailed === true){
                    message = 'CSV file has been sent to your email';
                }else{
                    message = 'CSV file download will begin shortly...';
                    setTimeout(function () { window.location.href = link; }, 1000);
                }
                Alert.alert('success', message);
            }
        }

    }).always(function(){
        if(!done){
            progress = setInterval(frame, frameInterval);
            poll = setTimeout(pollServer, pollInterval);
        }else{
            clearTimeout(poll);
        }
    });*/
}

function resetProgressBar() {
    alert('BAR => done: '+done + ' | running: '+running + ' | status: '+status + ' | count: '+count);
    progressBar.style.textAlign = 'center';
    progressBar.style.color = '#00264d';
    var width = 0, report = '0%';
    if(done === true){
        if(status >= 100 && count > 0 ){
            width = 100;  report = "Data retrieval completed!";
        }else if(count <= 0){
            width = 0;    report = "There are no comments";
        }
    }
    progressBar.style.width = width + '%';
    progressBar.innerHTML = report;
}

function setButtonStates() {
    alert('BTN => done: '+done + ' | running: '+running + ' | status: '+status + ' | count: '+count);
    document.getElementById("btxStart").style.display = running ? 'none' : 'block';
    document.getElementById("btxWork").style.display = running ? 'block' : 'none';
    document.getElementById("btn").disabled = running;
}

function endProcess() {
    running = false;
    resetProgressBar();       setButtonStates();
    clearInterval(progress);  clearTimeout(poll);
}

function frame() {
    var length, steps;

    if (currentWidth < status && parseFloat(currentWidth).toFixed(0) < 100){
        length = !done ? status - currentWidth : fixedLength;
        steps = pollInterval / frameInterval;
        currentWidth += length / steps;
        progressBar.innerHTML = progressBar.style.width = currentWidth.toFixed(0) + '%';

    } else {
        if(Number(status) === 0){ return; }

        done = true;  endProcess();
        clearInterval(progress);
        return null;
    }
}

var Alert = {
    create: function() {
        var container = function(html){
            return $('<div></div>')
                .attr({class:'alerts'}).css({visibility:'hidden'}).html(html);
        };
        var icon = function(){
            return $('<span></span>').attr({class:'alerts-icon'});
        };
        var closeBtn = function(){
            return $('<span></span>')
                .attr({class:'x-close', onclick:'Alert.close()'})
                .css({cursor:'pointer'})
                .html('&times;');
        };
        var messageBox = function(){
            return $('<div></div>').attr({class:'alerts-text'}).html('&nbsp;');
        };

        $(".form-content").prepend(
            container( icon() ).append( closeBtn() ).append( messageBox() )
        );
    },
    alert: function(status, message) {
        clearTimeout(error);

        var statesProps = {
            success:{icon:'&#10004;'}, info:{icon:'&#8505;'},
            warning:{icon:'&#9888'}, error:{icon:'&#10060'}
        };
        var states = Object.keys(statesProps);

        states.forEach(function (e, i) {
            $('.alerts').removeClass('alerts-'+e);
        });

        if(states.indexOf(status) < 0){ status = 'error'; }
        if(isEmpty(message)){ message = ''; }

        $('.alerts-icon').html(statesProps[status].icon);
        $('.alerts-text').text(message);
        $('.alerts').addClass('alerts-'+status).css({visibility:'visible'});

        error = setTimeout(function () { Alert.close(); }, 5000);
    },
    close: function () {
        $('.alerts').css({visibility:'hidden'});
    }
};

var CheckboxGroup = {
    ID: [],

    create: function() {
        var fields = [
            {id:'author', label:'Author'}, {id:'title', label:'Title'}, {id:'rating', label:'Star Rating'},
            {id:'date', label:'Date'}, {id:'comment', label:'Comment'}, {id:'link', label:'Comment Link'}
        ];

        var span = function(html){
            return $('<span></span>')
                .attr({class:'checkers-box'})
                .html(html);
        };

        var checkbox = function(c, id){
            return $('<input/>')
                .attr({type:'checkbox', class:c, name:id, id:id})
                .prop({checked:true});
        };

        var label = function(id, text){
            return $('<label></label>')
                .attr({for:id})
                .text(text);
        };

        var checkboxGroup = [], c = 'f_';
        fields.forEach(function (e, i) {
            var id = c + e.id, text = e.label;
            checkboxGroup.push(
                span(checkbox(c, id)).append(label(id, text))
            );
            CheckboxGroup.ID.push(id);
        });

        $(".input-box.checkbox").append(checkboxGroup);
    },

    update: function () {
        var checked = $('#f_all').prop('checked');
        CheckboxGroup.ID.forEach(function (e, i) {
            var el = $('#'+e), on = el.prop('checked');
            el.prop({disabled: checked, checked: on || true});
        });

    }
};

var sampleURLs = {
    create: function() {
        var URLs = [
            {category:'Apple', url: 'https://itunes.apple.com/us/app/grubhub-food-delivery-takeout/id302920553'},
            {category:'Amazon', url: 'https://www.amazon.com/eero-Home-WiFi-System-Pack/dp/B00XEW3YD6/ref=sr_1_1?s=pc&ie=UTF8&qid=1489250467&sr=1-1&keywords=eero&th=1#customerReviews'},
            {category:'Youtube', url: 'https://www.youtube.com/watch?v=BjZbAzUM9Ao'}
        ];

        var tr = function(html){ return $('<tr></tr>').html(html); };

        var td1 = function(category){
            return $('<td></td>').css({'vertical-align':'top'}).text(category)
        };

        var td2 = function(url){
            return $('<td></td>').css({'padding-left':'10px'}).html(
                $('<a></a>').attr({href:url, target:'_blank'}).text(url)
            )
        };

        var samples = [];
        URLs.forEach(function (e, i) {
            samples.push(
                tr(td1(e.category)).append(td2(e.url))
            );
        });

        $("#sampleURLs").append(samples);
    }
};

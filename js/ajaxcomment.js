function AjaxComment_serialize(form) {
    var field, l, s = [];
    if (typeof form == 'object' && form.nodeName == "FORM") {
        var len = form.elements.length;
        for (var i = 0; i < len; i++) {
            field = form.elements[i];
            if (field.name && !field.disabled && field.type != 'file' && field.type != 'reset' && field.type != 'submit' && field.type != 'button') {
                if (field.type == 'select-multiple') {
                    l = form.elements[i].options.length;
                    for (var j = 0; j < l; j++) {
                        if (field.options[j].selected) s[s.length] = encodeURIComponent(field.name) + "=" + encodeURIComponent(field.options[j].value);
                    }
                } else if ((field.type != 'checkbox' && field.type != 'radio') || field.checked) {
                    s[s.length] = encodeURIComponent(field.name) + "=" + encodeURIComponent(field.value);
                }
            }
        }
    }
    return s.join('&').replace(/%20/g, '+');
}

function AjaxComment_post(url, data, callback) {
    var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
    xhr.open('POST', url);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4) callback && callback(xhr);
    };
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send(data);
    return xhr;
}

function _id(id) {
    return document.getElementById(id);
}

function parseToDOMs(str) {
    var div = document.createElement('div');
    div.innerHTML = str;
    return div.childNodes;
}

function appendChildHtml(refchild, html) {
    var childs = parseToDOMs(html);
    for (var i = 0; i < childs.length; i++) {
        if (childs[i].nodeType == 1) {
            refchild.appendChild(childs[i]);
        } else if (childs[i].nodeType == 3 && childs[i].textContent.length > 2) {
            refchild.innerHTML += childs[i].textContent;
        }
    }
}

function insertBeforeHtml(refchild, refchild2, html) {
    var childs = parseToDOMs(html);
    for (var i = 0; i < childs.length; i++) {
        if (childs[i].nodeType == 1) {
            refchild.insertBefore(childs[i], refchild2)
        } else if (childs[i].nodeType == 3 && childs[i].textContent.length > 2) {
            refchild.innerHTML = childs[i].textContent + refchild.innerHTML;
        }
    }
}

function comment_append(content_html) {
    var new_id = 0;
    var regx = content_html.match(/id(\s+|)=(\s+|)("|'|)((.*?)comment-\d+)("|'|\s+|>)/g);
    if (regx) {
        var tmp = regx[0];
        new_id = tmp.match(/\d+/g)[0];
        id_format = tmp.replace(new_id, '{id}').replace(/\s+/g, '').replace(/(id=|"|')/g, '');
    }

    if (new_id == 0) content_html += success_div;

    var form = _id(respond_id).getElementsByTagName('form')[0];
    form.getElementsByTagName('textarea')[0].value = '';

    if (document.getElementsByClassName(comment_list_class_one).length == 0) {
        var comment_list = document.createElement(comment_list_element);
        comment_list.className = comment_list_class;
        _id(respond_id).parentNode.insertBefore(comment_list, _id(respond_id));
    }

    if (_id('comment-parent') == undefined) {
        appendChildHtml(document.getElementsByClassName(comment_list_class_one)[0], content_html);
    } else {
        var parent_id = id_format.replace('{id}', _id('comment-parent').value);
        TypechoComment.cancelReply();

        if (_id(parent_id).getElementsByClassName(comment_children_list_class_one).length == 0) {
            var children_list = document.createElement(comment_children_list_element);
            children_list.className = comment_children_list_class;
            _id(parent_id).appendChild(children_list);
        }

        var refchild = _id(parent_id).getElementsByClassName(comment_children_list_class_one)[0];
        if (refchild.innerHTML.indexOf(comment_list_class_one) != -1) {
            refchild = refchild.getElementsByClassName(comment_list_class_one)[0];
        } else {
            var comment_list = document.createElement(comment_list_element);
            comment_list.className = comment_list_class;
            refchild.appendChild(comment_list);
            refchild = comment_list;
        }
        if (comments_order == 'DESC') {
            var refchild2 = refchild.childNodes[0];
            insertBeforeHtml(refchild, refchild2, content_html);
        } else {
            appendChildHtml(refchild, content_html);
        }
    }

    if (new_id > 0) {
        appendChildHtml(_id(id_format.replace('{id}', new_id)), success_div);
        location.hash = '#' + id_format.replace('{id}', new_id);
    }
}

function registAjaxCommentEvent() {
	if (respond_id == null || ajaxcomment_url == null)
	{
		return;
	}
    var r = _id(respond_id);
    if (null != r) {
        var forms = r.getElementsByTagName('form');
        if (forms.length > 0) {
            if (_id('AjaxComment_loading') == undefined) {
                appendChildHtml(forms[0].getElementsByTagName('textarea')[0].parentNode, loading_div);
            }
            if (_id('AjaxComment_error') == undefined) {
                appendChildHtml(forms[0].getElementsByTagName('textarea')[0].parentNode, error_div);
            }

            forms[0].onsubmit = function() {
                var form = _id(respond_id).getElementsByTagName('form')[0];
                _id('AjaxComment_loading').style.display = 'block';
                _id('AjaxComment_error').style.display = 'none';
                AjaxComment_post(ajaxcomment_url, AjaxComment_serialize(form),
                function(xhr) {
                    _id('AjaxComment_loading').style.display = 'none';
                    if (xhr.status == 200) {
                        comment_append(xhr.responseText);
                    } else if (xhr.status == 405) {
                        _id('AjaxComment_error').style.display = 'block';
                        _id('AjaxComment_msg').innerHTML = xhr.responseText;
                    } else {
                        alert('Ajax 未知错误');
                    }
                });
                return false;
            }
        }
    }
}

if (typeof(jQuery) != 'undefined' && jQuery.support.pjax) {
    jQuery(document).on('pjax:success',
    function(event, data, status, xhr, options) {
        var ma = data.match(/var(\s+)(respond_id(\s+|)=(\s+|)("|'|)respond-post-(\d+)("|'|)(\s+|);)/i);
        if (ma) {
            eval(ma[2]);
        } else {
            respond_id = null
        }
        var ma = data.match(/var(\s+)(ajaxcomment_url(\s+|)=(\s+|)("|'|)(.*?)("|'|)(\s+|);)/i);
        if (ma) {
            eval(ma[2]);
        } else {
            ajaxcomment_url = null
        }
        registAjaxCommentEvent();
    });
}

registAjaxCommentEvent();
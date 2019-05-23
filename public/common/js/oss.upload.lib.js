/**
 * 阿里云OSS上传公共类
 * created by ikool 2018-11-5
 */

var ossUpload = function () {
    this.accessid = '';
    this.host = '';
    this.policyBase64 = '';
    this.signature = '';
    this.callbackbody = '';
    this.filename = '';
    this.key = '';
    this.expire = 0;
    this.g_object_name = {};
    this.fixedname = '';
    this.g_object_name_type = ''; //random_name | local_name | fixed
    this.serverUrl = '';
    this.env = '';
    this.defaultOpt = {
        show_progress_bar: false,
        width: 0,
        height: 0,
        is_multi: false,
        wh_model: '', //eq、gt、lt、gte、same
        file_name_type: 'random_name', //random_name、local_name、fixed
        fixedname: '',
        container_id: 'container',
        browse_button: 'selectfiles',
        callback: function () {
        },
        serverUrl: '/oss/get_token',
        max_file_size: '100mb',
        mime_types: [{title: "Image files", extensions: "jpg,gif,png,bmp"}] //[{title: "Image files", extensions: "jpg,gif,png,bmp"}, { title : "Zip files", extensions : "zip,rar" }]
    };

    this.send_request = function () {
        var xmlhttp = null;
        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else if (window.ActiveXObject) {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        if (xmlhttp != null) {
            xmlhttp.open("GET", this.serverUrl, false);
            xmlhttp.send(null);
            return xmlhttp.responseText
        } else {
            alert("Your browser does not support XMLHTTP.");
        }
    };

    this.get_signature = function () {
        //可以判断当前expire是否超过了当前时间,如果超过了当前时间,就重新取一下.3s 做为缓冲
        var now = Date.parse(new Date()) / 1000;
        if (this.expire < now + 3) {
            var body = this.send_request();
            var obj = eval("(" + body + ")");
            this.host = obj['host'];
            this.policyBase64 = obj['policy'];
            this.accessid = obj['accessid'];
            this.signature = obj['signature'];
            this.expire = parseInt(obj['expire']);
            this.callbackbody = obj['callback'];
            this.key = obj['dir'];
            this.env = obj['env'];
            return true;
        }
        return false;
    };

    this.random_string = function (len) {
        len = len || 32;
        var chars = 'ABCDEFGHJKMNPQRSTWXYZabcdefhijkmnprstwxyz2345678';
        var maxPos = chars.length;
        var pwd = '';
        for (var i = 0; i < len; i++) {
            pwd += chars.charAt(Math.floor(Math.random() * maxPos));
        }
        return pwd;
    };

    this.get_suffix = function (filename) {
        var pos = filename.lastIndexOf('.');
        var suffix = '';
        if (pos != -1) {
            suffix = filename.substring(pos);
        }
        return suffix;
    };

    this.calculate_object_name = function (file) {
        var prefix = this.key + (this.env == 'dev' ? 'dev/' : '');
        if (this.g_object_name_type == 'local_name') {
            this.g_object_name[file.id] = prefix + "${filename}";
        } else if (this.g_object_name_type == 'fixed') {
            this.g_object_name[file.id] = prefix + this.fixedname + ((this.fixedname.indexOf('.') == -1) ? this.get_suffix(file.name) : '');
        } else {
            //random_name
            this.g_object_name[file.id] = prefix + this.random_string(32) + this.get_suffix(file.name);
        }
        console.log('[filename]' + file.name + ' [g_object_name]' + this.g_object_name[file.id]);
        return '';
    };

    this.get_uploaded_object_name = function (file) {
        if (this.g_object_name_type == 'local_name') {
            var tmp_name = this.g_object_name[file.id];
            tmp_name = tmp_name.replace("${filename}", file.name);
            return tmp_name;
        } else {
            return this.g_object_name[file.id];
        }
    };

    this.set_upload_param = function (up, file) {
        this.get_signature();
        if (file != null) {
            this.calculate_object_name(file)
            var new_multipart_params = {
                'key': this.g_object_name[file.id],
                'policy': this.policyBase64,
                'OSSAccessKeyId': this.accessid,
                'success_action_status': '200', //让服务端返回200,不然，默认会返回204
                'callback': this.callbackbody,
                'signature': this.signature,
            };
            console.log(new_multipart_params);
            up.setOption({
                'url': this.host,
                'multipart_params': new_multipart_params
            });
        }
        up.start();
    };

    this.setOption = function (option) {
        for (var x in option) {
            this.defaultOpt[x] = option[x]
            //console.log(x, "|", option[x])
        }
    };

    this.init = function (option) {
        var _this = this;
        _this.setOption(option);
        console.log(_this.defaultOpt);
        this.g_object_name_type = _this.defaultOpt.file_name_type;
        this.fixedname = _this.defaultOpt.fixedname;
        this.serverUrl = _this.defaultOpt.serverUrl;

        var uploader = new plupload.Uploader({
            runtimes: 'html5,html4', //html5,flash,silverlight,html4
            browse_button: _this.defaultOpt.browse_button,
            multi_selection: _this.defaultOpt.is_multi,
            container: document.getElementById(_this.defaultOpt.container_id),
            flash_swf_url: '/common/js/plupload-2.1.2/js/Moxie.swf',
            silverlight_xap_url: '/common/js/plupload-2.1.2/js/Moxie.xap',
            url: 'http://oss.aliyuncs.com',
            filters: {
                mime_types: _this.defaultOpt.mime_types,
                max_file_size: _this.defaultOpt.max_file_size, //最大只能上传10mb的文件
                prevent_duplicates: true //不允许选取重复文件
            },

            init: {
                PostInit: function () {
                },

                FilesAdded: function (up, files) {
                    if (_this.defaultOpt.show_progress_bar) {
                        var parent = document.getElementById(_this.defaultOpt.container_id).parentNode;
                        var warp = document.getElementById('progress_bar_warp');
                        if (warp == null) {
                            warp = document.createElement('div');
                            warp.id = 'progress_bar_warp';
                            parent.appendChild(warp)
                        }
                        var html = '';
                        plupload.each(files, function (file) {
                            html += '<div id="' + file.id + '">' + file.name + ' (' + plupload.formatSize(file.size) + ')<b></b>'
                                + '<div class="progress" style="width:200px;"><div class="progress-bar" style="width: 0%"></div></div>'
                                + '</div>';
                        });
                        warp.innerHTML = html;
                    }

                    console.log(files)
                    console.log("FilesAdded");
                    var flag = true;
                    for (var x = 0; x < files.length; x++) {
                        if (_this.defaultOpt.wh_model != '') {
                            (function () {
                                var file = files[x];
                                //console.log(file)
                                var reader = new FileReader();
                                reader.readAsDataURL(file.getNative());
                                reader.onload = (function (e) {
                                    var image = new Image();
                                    image.src = e.target.result;
                                    image.onload = function () {
                                        if (_this.defaultOpt.wh_model == 'same') {
                                            if (this.width == this.height) {
                                                //NOP
                                            } else {
                                                flag = false;
                                                alert('文件：' + file.name + '图片的宽高尺寸必须相等');
                                            }
                                        } else if (_this.defaultOpt.wh_model == 'eq') {
                                            if (this.width == _this.defaultOpt.width && this.height == _this.defaultOpt.height) {
                                                //NOP
                                            } else {
                                                flag = false;
                                                alert('文件：' + file.name + '图片尺寸必须等于' + _this.defaultOpt.width + 'x' + _this.defaultOpt.height + '！');
                                            }
                                        } else if (_this.defaultOpt.wh_model == 'gt') {
                                            if (this.width > _this.defaultOpt.width && this.height > _this.defaultOpt.height) {
                                                //NOP
                                            } else {
                                                flag = false;
                                                alert('文件：' + file.name + '图片尺寸必须宽度大于' + _this.defaultOpt.width + '且高度大于' + _this.defaultOpt.height + '！');
                                            }
                                        } else if (_this.defaultOpt.wh_model == 'lt') {
                                            if (this.width < _this.defaultOpt.width && this.height < _this.defaultOpt.height) {
                                                //NOP
                                            } else {
                                                flag = false;
                                                alert('文件：' + file.name + '图片尺寸必须宽度小于' + _this.defaultOpt.width + '且高度小于' + _this.defaultOpt.height + '！');
                                            }
                                        } else if (_this.defaultOpt.wh_model == 'gte') {
                                            if (this.width >= _this.defaultOpt.width && this.height >= _this.defaultOpt.height) {
                                                //NOP
                                            } else {
                                                flag = false;
                                                alert('文件：' + file.name + ' 图片尺寸必须宽度大于等于' + _this.defaultOpt.width + '且高度大于等于' + _this.defaultOpt.height + '！');
                                            }
                                        }
                                    };
                                });
                            })()
                        }
                    }

                    window.setTimeout(function () {
                        console.log(flag)
                        if (!flag) {
                            uploader.splice();
                            uploader.refresh();
                        } else {
                            uploader.start();
                        }
                    }, 100);
                },

                BeforeUpload: function (up, file) {
                    console.log("BeforeUpload");
                    _this.set_upload_param(up, file);
                },

                UploadProgress: function (up, file) {
                    if (_this.defaultOpt.show_progress_bar) {
                        var d = document.getElementById(file.id);
                        d.getElementsByTagName('b')[0].innerHTML = '<span>' + file.percent + "%</span>";
                        var prog = d.getElementsByTagName('div')[0];
                        var progBar = prog.getElementsByTagName('div')[0]
                        progBar.style.width = 2 * file.percent + 'px';
                        progBar.setAttribute('aria-valuenow', file.percent);
                    }
                },

                FileUploaded: function (up, file, info) {
                    if (info.status == 200) {
                        var imgurl = _this.host + '/' + _this.get_uploaded_object_name(file);
                        console.log(file.name + '|' + imgurl);
                        _this.defaultOpt.callback(imgurl);
                    } else {
                        console.log('Info:' + info.response);
                    }
                },

                Error: function (up, err) {
                    if (err.code == -600) {
                        alert("选择的文件太大了,最大不能超过" + _this.defaultOpt.max_file_size);
                    } else if (err.code == -601) {
                        alert("选择的文件后缀不对,可以根据应用情况，在upload.js进行设置可允许的上传文件类型");
                    } else if (err.code == -602) {
                        alert("这个文件已经上传过一遍了");
                    } else {
                        alert("Error:" + err.response);
                    }
                }
            }
        });
        uploader.init();
    }
}

 
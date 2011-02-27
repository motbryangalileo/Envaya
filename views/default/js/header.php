function Class(){}function makeClass(b){b=b||Class;var c=function(){this.init.apply(this,arguments)};var a=function(){};a.prototype=b.prototype;c.prototype=new a;return c}function addEvent(c,b,a){if(c.addEventListener){c.addEventListener(b,a,false)}else{c.attachEvent("on"+b,a)}}function removeEvent(c,b,a){if(c.removeEventListener){c.removeEventListener(b,a,false)}else{c.detachEvent("on"+b,a)}}var _jsonCache={};function fetchJson(url,fn){if(_jsonCache[url]){setTimeout(function(){fn(_jsonCache[url])},1);return null}else{var xhr=(window.ActiveXObject&&!window.XMLHttpRequest)?new ActiveXObject("Msxml2.XMLHTTP"):new XMLHttpRequest();xhr.onreadystatechange=function(){if(xhr.readyState==4&&xhr.status==200){var $data;eval("$data = "+xhr.responseText);_jsonCache[url]=$data;fn($data)}};xhr.open("GET",url,true);xhr.send(null);return xhr}}function bind(b,a){return function(){return a(b)}}function removeChildren(a){while(a.firstChild){a.removeChild(a.firstChild)}}function removeElem(a){if(a.parentNode){a.parentNode.removeChild(a)}}function createElem(){var f=arguments[0];var d=document.createElement(f);for(var c=1;c<arguments.length;c++){var a=arguments[c];switch(typeof(a)){case"string":d.appendChild(document.createTextNode(a));break;case"object":if(a!=null){if(a.nodeName){d.appendChild(a)}else{for(var b in a){if(a.hasOwnProperty(b)){var e=a[b];if(typeof(e)=="function"){addEvent(d,b,e)}else{d[b]=a[b]}}}}}break}}return d}window.dirty=false;function setDirty(a){if(a&&!window.submitted){if(!window.onbeforeunload){window.onbeforeunload=function(){return __["page:dirty"]}}}else{window.onbeforeunload=null}window.dirty=a;return true}var _submitFns=[];function addSubmitFn(a){_submitFns.push(a)}function setSubmitted(){setDirty(false);for(var a=0;a<_submitFns.length;a++){_submitFns[a]()}window.submitted=true;return true}function addImageLink(a){var b=/[\=\/](\d+)\/([\w\.]+)\/([\w\.]+)/.exec(a.src);if(b&&b[3]!="large.jpg"){a.style.cursor="pointer";addEvent(a,"click",function(){window.location="/pg/large_img?owner="+(b[1])+"&group="+b[2]})}}function addImageLinks(a){if(a){var d=a.getElementsByTagName("img");for(var c=0;c<d.length;c++){var b=d[c];if(b.parentNode.nodeName!="A"){addImageLink(b)}}}};
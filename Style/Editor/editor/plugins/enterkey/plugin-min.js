KISSY.Editor.add("enterkey",function(o){var j=KISSY,h=j.Editor,k=j.UA,q=/^h[1-6]$/,r=h.XHTML_DTD,l=j.Node,s=j.Event,t=h.Walker,u=h.ElementPath;h.enterBlock||function(){function p(a){s.on(a.document,"keydown",function(b){if(b.keyCode===13)if(!b.shiftKey){a.fire("save");var f=a.execCommand("enterBlock");a.fire("save");f!==false&&b.preventDefault()}})}p.enterBlock=function(a){var b;b=a.getSelection().getRanges();for(var f=b.length-1;f>0;f--)b[f].deleteContents();b=b[0];f=b.document;if(b.checkStartOfBlock()&&
b.checkEndOfBlock()){var c=(new u(b.startContainer)).block;if(c&&(c._4e_name()=="li"||c.parent()._4e_name()=="li"))if(a.hasCommand("outdent")){a.fire("save");a.execCommand("outdent");a.fire("save");return true}else return false}var g=b.splitBlock("p");if(!g)return true;a=g.previousBlock;c=g.nextBlock;var n=g.wasStartOfBlock,m=g.wasEndOfBlock,e;if(c){e=c.parent();if(e._4e_name()=="li"){c._4e_breakParent(e);c._4e_move(c._4e_next(),true)}}else if(a&&(e=a.parent())&&e._4e_name()=="li"){a._4e_breakParent(e);
b.moveToElementEditablePosition(a._4e_next());a._4e_move(a._4e_previous())}if(!n&&!m){if(c._4e_name()=="li"&&(e=c._4e_first(t.invisible(true)))&&j.inArray(e._4e_name(),["ul","ol"]))(k.ie?new l(f.createTextNode("\u00a0")):new l(f.createElement("br"))).insertBefore(e);c&&b.moveToElementEditablePosition(c)}else{var d;if(a){if(a._4e_name()=="li"||!q.test(a._4e_name()))d=a._4e_clone()}else if(c)d=c._4e_clone();d||(d=new l("<p>",null,f));if(e=g.elementPath){g=0;for(var v=e.elements.length;g<v;g++){var i=e.elements[g];
if(i._4e_equals(e.block)||i._4e_equals(e.blockLimit))break;if(r.$removeEmpty[i._4e_name()]){i=i._4e_clone();d._4e_moveChildren(i);d.append(i)}}}k.ie||d._4e_appendBogus();b.insertNode(d);if(k.ie&&n&&(!m||!a[0].childNodes.length)){b.moveToElementEditablePosition(m?a:d);b.select()}b.moveToElementEditablePosition(n&&!m?c:d)}if(!k.ie)if(c){d=new l(f.createElement("span"));d.html("&nbsp;");b.insertNode(d);d._4e_scrollIntoView();b.deleteContents()}else d._4e_scrollIntoView();b.select();return true};h.EnterKey=
p}();o.addCommand("enterBlock",{exec:h.EnterKey.enterBlock});h.EnterKey(o)},{attach:false});
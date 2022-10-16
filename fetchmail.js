/*******************************************************************************
 * Fetchmail Roundcube Plugin (Roundcube version 1.0-beta and above)
 * This software distributed under the terms of the GNU General Public License 
 * as published by the Free Software Foundation
 * Further details on the GPL license can be found at
 * http://www.gnu.org/licenses/gpl.html
 * By contributing authors release their contributed work under this license 
 * For more information see README.md file
 ******************************************************************************/
function fetchmail_toggle_folder() {
   switch ($('#fetchmailprotocol').val()) {
       case "IMAP":
           $("#fetchmail_folder_display").show();
           break;
       default:
	       $("#fetchmail_folder_display").hide();
   }
}

if (window.rcmail) {
	rcmail.addEventListener('init', function (evt) {
		if (rcmail.env.action == 'plugin.fetchmail') {
			rcmail.section_select = function(list) {
				var win, id = list.get_single_selection();
	
				if (id && (win = this.get_frame_window(this.env.contentframe))) {
					this.location_href({_action: 'plugin.fetchmail.edit', _id: id, _framed: 1}, win, true);
				}
				this.enable_command('fetchmail.delete', true);
			}
		    
			rcmail.register_command('fetchmail.delete', function() {
				var id = rcmail.sections_list.get_single_selection();
				rcmail.confirm_dialog(rcmail.get_label('fetchmail.fetchmaildelconfirm'), 'delete', function(e, ref) {
					var post = '_act=delete&_id=' + ref.sections_list.rows[id].uid,
					lock = ref.set_busy(true, 'loading');

					ref.http_post('plugin.fetchmail.delete', post, lock);
				});
			});
			rcmail.register_command('fetchmail.add', function() {
				if ((win = rcmail.get_frame_window(rcmail.env.contentframe))) {
					rcmail.location_href({_action: 'plugin.fetchmail.edit', _id: '', _framed: 1}, win, true);
				}
				rcmail.enable_command('fetchmail.delete', false);
			}, true);
			
			if(rcmail.sections_list.rowcount < rcmail.env.fetchmail_limit) {
		    	rcmail.enable_command('fetchmail.add', true);
		    } else {
		    	rcmail.enable_command('fetchmail.add', false);
		    }

		}
		if(rcmail.env.action == 'plugin.fetchmail.edit') {
			rcmail.register_command('fetchmail.save', function() {
				var form = document.getElementById('fetchmailform');
				$('#fetchmailform .invalid-feedback').remove();
				if(form.checkValidity()) {
		            var settings = $('#fetchmailform').serialize();
					lock = rcmail.set_busy(true, 'loading');
		            rcmail.http_post('plugin.fetchmail.save', settings, lock);
				}
				form.classList.add('was-validated');
			});
			rcmail.enable_command('fetchmail.save', true);
		}
	});
	rcmail.addEventListener('plugin.fetchmail.save.callback', function(e) {
		if(e.result == "done") {
			var rowid = "rcmrow"+e.id;
			if(parent.window.document.getElementById(rowid)) {
				parent.window.document.getElementById(rowid).getElementsByTagName('td')[0].innerText = e.title;
			} else {
				parent.window.rcmail.sections_list.insert_row({id:rowid,uid:e.id,className:"fetchmail-account",cols:[{className:"section",innerHTML:e.title}]});
		        parent.window.rcmail.sections_list.select_row(e.id);
			}
		    rcmail.display_message(rcmail.gettext('successfullysaved','fetchmail'), 'confirmation');
		    if(parent.window.rcmail.sections_list.rowcount < parent.window.rcmail.env.fetchmail_limit) {
		    	parent.window.rcmail.enable_command('fetchmail.add', true);
		    } else {
		    	parent.window.rcmail.enable_command('fetchmail.add', false);
		    }
		    parent.window.document.getElementById('fetchmail-quota').getElementsByClassName('count')[0].innerText = parent.window.rcmail.sections_list.rowcount+"/"+parent.window.rcmail.env.fetchmail_limit;
			parent.window.document.getElementById('fetchmail-quota').getElementsByClassName('value')[0].setAttribute("style","width:"+((parent.window.rcmail.sections_list.rowcount/parent.window.rcmail.env.fetchmail_limit)*100)+"%");

		} else if (e.result == "dnserror") {
			/* Override Client-Side Validation to display the DNS error properly, see https://github.com/twbs/bootstrap/issues/32733 */
			$('#fetchmailform').removeClass('was-validated');
			$('#fetchmailform input').addClass('is-valid');
			$('#fetchmailform select').addClass('is-valid');
			$('#fetchmailserver').removeClass('is-valid').addClass('is-invalid');
			const fb = document.createElement("div")
			fb.classList.add("invalid-feedback");
			fb.innerText = e.message;
			document.getElementById('fetchmailserver').parentNode.appendChild(fb);
		} else if (e.result == "dberror") {
			rcmail.display_message(e.message, 'error');
		}
	});
	rcmail.addEventListener('plugin.fetchmail.delete.callback', function(e) {
		if(e.result == "done") {
			rcmail.sections_list.remove_row(e.id);
			if ((win = rcmail.get_frame_window(rcmail.env.contentframe))) {
				win.location.href = rcmail.env.blankpage;
			}
			rcmail.display_message(rcmail.gettext('successfullydeleted','fetchmail'), 'confirmation');
			rcmail.enable_command('fetchmail.add', true);
			rcmail.enable_command('fetchmail.delete', false);
			document.getElementById('fetchmail-quota').getElementsByClassName('count')[0].innerText = rcmail.sections_list.rowcount+"/"+rcmail.env.fetchmail_limit;
			document.getElementById('fetchmail-quota').getElementsByClassName('value')[0].setAttribute("style","width:"+((rcmail.sections_list.rowcount/rcmail.env.fetchmail_limit)*100)+"%");
		} else if (e.result == "dberror") {
			rcmail.display_message(e.message, 'error');
		}
	});

}



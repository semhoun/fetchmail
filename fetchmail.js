/*******************************************************************************
 * Fetchmail Roundcube Plugin (Roundcube version 1.0-beta and above)
 * This software distributed under the terms of the GNU General Public License 
 * as published by the Free Software Foundation
 * Further details on the GPL license can be found at
 * http://www.gnu.org/licenses/gpl.html
 * By contributing authors release their contributed work under this license 
 * For more information see README.md file
 ******************************************************************************/

function fetchmail_edit(id) {
	window.location.href = '?_task=settings&_action=plugin.fetchmail&_id=' + id;
}
function row_del(id) {
	if (id == "") {
		parent.rcmail.display_message(rcmail.gettext('textempty', 'fetchmail'),
			'error');
	} else {
		document.getElementById('fetch-table').deleteRow(
			document.getElementById('fetch_' + id).rowIndex);
		rcmail.http_request('plugin.fetchmail.del', '_id=' + id, true);
		if (document.getElementById('fetch-table')
			.getElementsByTagName("TBODY").item(0).rows.length == 0) {
			var tbody = document.getElementById('fetch-table')
				.getElementsByTagName("TBODY").item(0);
			var row = document.createElement("TR");
			var cell = document.createElement("TD");
			var text = document.createTextNode(rcmail.gettext('nofetch',
				'fetchmail'));
			cell.setAttribute('colspan', '4');
			cell.appendChild(text);
			row.appendChild(cell);
			tbody.appendChild(row);
		}
		$('#fetchmail_items_number').text(
			$('#fetchmail_items_number').text() - 1);
		parent.rcmail.display_message(rcmail.gettext('successfullydeleted',
			'fetchmail'), 'confirmation');
	}
}
function row_edit(id, active) {
	if (id == "") {
		parent.rcmail.display_message(rcmail.gettext('textempty', 'fetchmail'),
			'error');
	} else {
		if (active == 1) {
			var active = 0;
			document.getElementById('td_' + id).setAttribute('onclick',
				'row_edit(' + id + ',' + active + ');');
			document.getElementById('img_' + id).src = 'plugins/fetchmail/skins/default/disabled.png';
			rcmail.http_request('plugin.fetchmail.disable', '_id=' + id, true);
			parent.rcmail.display_message(rcmail.gettext(
				'successfullydisabled', 'fetchmail'), 'confirmation');
		} else {
			var active = 1;
			document.getElementById('td_' + id).setAttribute('onclick',
				'row_edit(' + id + ',' + active + ');');
			document.getElementById('img_' + id).src = 'plugins/fetchmail/skins/default/enabled.png';
			rcmail.http_request('plugin.fetchmail.enable', '_id=' + id, true);
			parent.rcmail.display_message(rcmail.gettext('successfullyenabled',
				'fetchmail'), 'confirmation');
		}
	}
}
function fetchmail_toggle_folder() {
	switch ($('#fetchmailprotocol').val()) {
		case "IMAP":
			$("#fetchmail_folder_display").show()
			break;
		default:
			$("#fetchmail_folder_display").hide()
	}
}
function fetchmail_callback_function() {
	window.location.reload();
}
if (window.rcmail) {
	rcmail.addEventListener('init', function (evt) {
		rcmail.register_command('plugin.fetchmail.save', function () {
			var input_server = rcube_find_object('_fetchmailserver');
			var input_user = rcube_find_object('_fetchmailuser');
			var input_pass = rcube_find_object('_fetchmailpass');
			if (input_server.value == "" || input_user.value == ""
				|| input_pass.value == "") {
				parent.rcmail.display_message(rcmail.gettext('textempty',
					'fetchmail'), 'error');
			} else {
				//document.forms.fetchmailform.submit();
				var settings = $('form[name*="fetchmailform"]').serialize();
				rcmail.http_post('plugin.fetchmail.save', settings);
			}
		}, true);

	})
	rcmail.addEventListener('plugin.fetchmailcallback', fetchmail_callback_function);
}

import $ from 'jquery';
import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';

/*
 * Select2 and AdminLTE are plain browser scripts that read jQuery and Bootstrap from the window object.
 * Expose them before those scripts are evaluated (imports run in order).
 */
window.$ = $;
window.jQuery = $;
window.bootstrap = bootstrap;
window.Swal = Swal;

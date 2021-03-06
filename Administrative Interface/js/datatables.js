/**   ___ ___       ___ _______     ______                        __
 *   |   Y   .-----|   |   _   |   |   _  \ .-----.--------.---.-|__.-----.
 *   |.  |   |  _  |.  |.  1   |   |.  |   \|  _  |        |  _  |  |     |
 *   |.  |   |_____|.  |.  ____|   |.  |    |_____|__|__|__|___._|__|__|__|
 *   |:  1   |     |:  |:  |       |:  1    /
 *    \:.. ./      |::.|::.|       |::.. . /
 *     `---'       `---`---'       `------'
 *
 * Copyright (C) 2016-2018 Ernani José Camargo Azevedo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * VoIP Domain dataTables JavaScript customization script.
 *
 * @author     Ernani José Camargo Azevedo <azevedo@voipdomain.io>
 * @version    1.0
 * @package    VoIP Domain
 * @subpackage Core
 * @copyright  2016-2018 Ernani José Camargo Azevedo. All rights reserved.
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html
 */

$(document).ready ( function ()
{
  /**
   * DataTables customization
   */
  if ( typeof $.fn.dataTable == 'function')
  {
    $.extend ( true, $.fn.dataTable.Buttons.defaults,
    {
      dom:
      {
        container:
        {
          tag: 'div',
          className: 'btn-group dt-buttons'
        },
        collection:
        {
          tag: 'div',
          className: 'btn-group dt-button-collection'
        },
        button:
        {
          tag: 'button',
          className: 'btn btn-default',
          active: 'active',
          disabled: 'disabled'
        },
        buttonLiner:
        {
          tag: '',
          className: ''
        }
      }
    });
    $.extend ( true, $.fn.dataTable.defaults,
    {
      order: [[ 1, 'asc' ]],
      processing: true,
      searching: true,
      autoWidth: false,
      responsive: true,
      dom: '<"dt-row"<"dt-left"<"#addbutton">l><"dt-right"f><"dt-center"<B>>>rt+<"dt-row"<"dt-left"i><"dt-right"p>>',
      drawCallback: function ( settings) {},
      mark: true,
      stateSave: true,
      initComplete: function ( settings, json)
      {
        if ( typeof $.fn.stickyTableHeaders == 'function')
        {
          $(this).stickyTableHeaders ( { scrollableArea: $('.wrapper')});
        }
        if ( typeof $.fn.select2 == 'function')
        {
          $('select[name="datatables_length"]').select2 ( { minimumResultsForSearch: Infinity});
        }
        $('.dataTables_filter').empty ().html ( '<label><form><div class="input-group hidden-sm"><input type="search" class="form-control" placeholder="Filtro..." aria-control="search"><div class="input-group-btn"><button class="btn btn-default" type="reset"><i class="fas fa-times"></i></button></div></div></form></label>');
        $('.dataTables_filter input[type="search"]').val ( settings.oSavedState.search.search).on ( 'keyup', function () { $('#datatables').data ( 'dt').search ( jQuery.fn.DataTable.ext.type.search.string ( this.value)).draw (); }).closest ( 'form').on ( 'submit', function ( e) { e.preventDefault (); return false; });
        $('.dataTables_filter button[type="reset"]').on ( 'click', function () { $('#datatables').data ( 'dt').search ( '').draw (); });
        $('.dataTables').find ( '*[data-toggle="tooltip"]').tooltip ();
      },
      buttons:
      [
        {
          extend: 'copyHtml5',
          text: '<i class="far fa-copy"></i>',
          titleAttr: 'Copiar',
          footer: true,
          exportOptions:
          {
            columns: '.export'
          }
        },
        {
          extend: 'pdfHtml5',
          text: '<i class="far fa-file-pdf"></i>',
          titleAttr: 'PDF',
          footer: true,
          exportOptions:
          {
            columns: '.export'
          }
        },
        {
          extend: 'excelHtml5',
          text: '<i class="far fa-file-excel"></i>',
          titleAttr: 'Excel',
          footer: true,
          exportOptions:
          {
            columns: '.export'
          }
        },
        {
          extend: 'csvHtml5',
          text: '<i class="far fa-file-alt"></i>',
          titleAttr: 'CSV',
          footer: false,
          exportOptions:
          {
            columns: '.export'
          }
        },
        {
          text: '<i class="far fa-file-code"></i>',
          titleAttr: 'JSON',
          footer: false,
          action: function ( e, dt, button, config)
                  {
                    $.fn.dataTable.fileSave ( new Blob ( [ JSON.stringify ( dt.buttons.exportData ( { columns: '.export'}))]), $.trim ( $('title').text () + '.json'));
                  }
        },
        {
          extend: 'print',
          text: '<i class="fas fa-print"></i>',
          titleAttr: 'Imprimir',
          footer: true,
          exportOptions:
          {
            columns: '.export'
          }
        }
      ],
      language:
      {
        sEmptyTable: 'Nenhum registro encontrado',
        sInfo: 'Exibindo _START_ a _END_ de _TOTAL_ registros',
        sInfoEmpty: 'Exibindo 0 a 0 de 0 registros',
        sInfoFiltered: '(filtrado de _MAX_ registros no total)',
        sInfoPostFix: '',
        sInfoThousands: '.',
        sLengthMenu: '_MENU_ por página',
        sLoadingRecords: 'Aguarde...',
        sProcessing: 'Processando...',
        sZeroRecords: 'Nenhum registro encontrado',
        sSearch: '',
        sSearchPlaceholder: 'Filtro',
        oPaginate:
        {
          sNext: 'Seguinte',
          sPrevious: 'Anterior',
          sFirst: 'Primeiro',
          sLast: 'Último'
        },
        oAria:
        {
          sSortAscending: ': Ordenar colunas de forma ascendente',
          sSortDescending: ': Ordenar colunas de forma descendente'
        },
        buttons:
        {
          copyTitle: 'Adicionado para área de tranferência',
          copyKeys: 'Pressione <i>Ctrl</i> ou <i>\u2318</i> + <i>C</i> para copiar os dados da tabela para a área de transferência.<br />Para cancelar, clique sobre esta mensagem ou pressione a tecla ESC.',
          copySuccess:
          {
            _: 'Total de %d registros',
            1: 'Total de 1 registro'
          }
        }
      }
    });
  }
});

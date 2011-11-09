var checkedTableRows = new Array();

function checkTableRow(checkbox, rowid)
{
  var index = checkedTableRows.indexOf(rowid);
  if (checkbox.checked) {
    if (index == -1)
      checkedTableRows.push(rowid);
  }
  else if (index != -1)
    checkedTableRows.splice(index, 1);
    
}

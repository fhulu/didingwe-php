function showOnSelect(select, index, subjectId, show)
{
  if (select.selectedIndex == index)
    showHide(subjectId, show);
  else
    showHide(subjectId, !show);
}
function showOnFirstSelect(select, subjectId, show)
{
  showOnSelect(select, 0, subjectId, show);
}
function showOnLastSelect(select, subjectId, show)
{
	showOnSelection(select, select.length - 1, subjectId, show);
}

function setSelected(select, subjectId)
{
  var obj = getElementByIdOrName(subjectId);
  obj.value = select.options[select.selectedIndex].text;
}

function setSelectedValue(select, subjectId)
{
  var obj = getElementByIdOrName(subjectId);
  obj.value = select.options[select.selectedIndex].value;
}
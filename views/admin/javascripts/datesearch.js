jQuery(document).bind("omeka:elementformload", function() {
  var $ = jQuery; // use noConflict version of jQuery as the short $ within this block

  var gregFirst=dateSearchGregorian.substr(0,1); // "G"
  var julFirst=dateSearchJulian.substr(0,1); // "J"
  var dateFirst=dateSearchDate.substr(0,1); // "D"

  var gregPrefix="["+gregFirst+"]"; // "[G]"
  var julPrefix="["+julFirst+"]"; // "[J]"
  var datePrefix=""; // empty

  // --------------------------------------------------------

  $("#dateSearchWrapper").remove();
  $("#save")
    .append("<span id='dateSearchWrapper'>"+
              "<div class='dateSearchButtons field'>"+
                "<div><label>"+dateSearchDateEntry+"</label></div><br>"+
                "<input id='dateSearchEdit' class='dateSearchHiddenEdit'>"+
                "<button class='dateSearchBtn' data-caltype='' >"+dateFirst+"</button>"+ // unspecific
                "<button class='dateSearchBtn' data-caltype='G'>"+gregFirst+"</button>"+ // Gregorian
                "<button class='dateSearchBtn' data-caltype='J'>"+julFirst+"</button>"+ // Julian
                "<input type='checkbox' id='dateSearchTimeSpan'> "+
                "<label for='dateSearchTimeSpan'>"+dateSearchTimeSpan+"</label> "+
                "<br><strong>"+dateSearchConvert+":</strong><br>"+
                "<a href='#' class='dateSearchConvLink' data-convto='G'>→ ["+dateSearchGregorian+"]</a> "+
                "<a href='#' class='dateSearchConvLink' data-convto='J'>→ ["+dateSearchJulian+"]</a>"+
              "</div>"+
            "</span>");

  // --------------------------------------------------------

  var currentTextArea = false;
  $("textarea").focus(function(e) { currentTextArea = $(this); })

  // --------------------------------------------------------

  var curCalType = "gregorian";
  var curPrefix = "";
  var curPickerStatus = "";

  $("#dateSearchEdit").calendarsPicker({
    showOnFocus: false,
    firstDay: 1,
		yearRange: 'any',
    dateFormat: "yyyy-mm-dd",
    clearText: dateSearchCancel,
    onShow: function(picker, inst) {
      picker.find('tbody').append("<tr><td colspan='7' class='calendars-status'>"+
                                  "<strong>"+curPickerStatus+"</strong>"+
                                  "</td></tr>");
    },
    onClose: function(dates) {
      // console.log('Closed with date(s): ' + dates);
      if (currentTextArea) {
         var newDate = $("#dateSearchEdit").val();
         if (newDate) { currentTextArea.replaceSelectedText(curPrefix+newDate); }
         $("#dateSearchEdit").val("");
       }
    }
  });

  // --------------------------------------------------------

  function evaluatePrefix(selText) {
    var prefix = selText.substr(0,4).toUpperCase();

    var prefixLetter = "";
    var croppedSelText = selText;

    if ( (prefix == gregPrefix+" ") || (prefix == julPrefix+" ") ) {
      prefixLetter = selText.substr(1,1);
      croppedSelText = selText.substr(4);
    }

    return {
      "prefix" : prefix,
      "prefixLetter" : prefixLetter,
      "croppedSelText" : croppedSelText
    };
  }

  // --------------------------------------------------------

  function calTypeFromType(letter) {
    var result="gregorian";
    if (letter) { result = ( letter == gregFirst ? "gregorian" : "julian" ); }
    return result;
  }

  // --------------------------------------------------------

  function prefixFromType(calType) {
    var result = "";
    switch (calType) {
      case gregFirst :
      case "gregorian" : result = gregPrefix+" "; break;
      case julFirst :
      case "julian" : result = julPrefix+" "; break;
    }
    return result;
  }

  function pickerFromType(calType) {
    var result = "";
    switch (calType) {
      default  : result = ""; break;
      case ""  : result = dateSearchDate; break;
      case "G" : result = dateSearchGregorian; break;
      case "J" : result = dateSearchJulian; break;
    }
    return result;
  }

  // --------------------------------------------------------

  $(".dateSearchBtn").click(function(e) {
    e.preventDefault();

    if (currentTextArea) {
      var calType = $(this).data("caltype");

      var sel = currentTextArea.getSelection();
      var selText = "";
      if (sel.start != sel.end) { selText = sel.text; }

      var overrideCalType = "";

      var evPrefix = evaluatePrefix(selText);
      if (evPrefix.prefixLetter) {
        overrideCalType = evPrefix.prefixLetter;
        selText = evPrefix.croppedSelText;
      }

      if ( (overrideCalType) && (overrideCalType != calType) ) {
        alert( dateSearchCantEdit.replace("%", pickerFromType(calType)) );
        return;
      }

      curCalType = calTypeFromType(calType);
      curPrefix = prefixFromType(calType);
      curPickerStatus = pickerFromType(calType);

      var isSpan = $("#dateSearchTimeSpan").is(':checked');
      if (selText.indexOf(" - ") >= 0) { isSpan=true; }

      $("#dateSearchEdit").val(selText);

      $("#dateSearchEdit").calendarsPicker("option", {
        rangeSelect: isSpan,
        calendar: $.calendars.instance(curCalType, dateSearchLocale)
      } );

      $("#dateSearchEdit").show().calendarsPicker("show").hide();
    }
    else { alert(dateSearchSelectFirst); }
  });

  // --------------------------------------------------------

  function canConvToJulian(date) {
    var result=true;

    var dateString=String(date);

    var year=parseInt(dateString.substr(0,4));
    var month=parseInt(dateString.substr(5,2));
    var day=parseInt(dateString.substr(8,2));

    if (year<1582) { result=false; }
    else if ( (year==1582) && (month<10) ) { result=false; }
    else if ( (year==1582) && (month==10) && (day<15) ) { result=false; }

    console.log(year+" | "+month+" | "+day+" = "+result);

    return result;
  }

  // --------------------------------------------------------

  function setPickerToGregJuli(thisDateEdit, to) {
    var myCalendar = $.calendars.instance(to, dateSearchLocale);
    thisDateEdit.calendarsPicker("option", { calendar: myCalendar } );
    curCalType = to;
    curPickerStatus = ( to == "gregorian" ? dateSearchGregorian : dateSearchJulian );
  }

  // --------------------------------------------------------

  $(".dateSearchConvLink").click(function(e) {
    e.preventDefault();

    if (currentTextArea) {
      var sel = currentTextArea.getSelection();
      var selText = "";
      if (sel.start != sel.end) {
        selText = sel.text;
        // console.log("selText "+selText);

        var convTo = $(this).data("convto");
        var convFrom = (convTo == "G" ? "J" : "G");
        // console.log("convFrom " + convFrom + " / " + "convTo " + convTo);

        var evPrefix = evaluatePrefix(selText);
        var overrideConvFrom = "";
        if (evPrefix.prefixLetter) {
          // convFrom = evPrefix.prefixLetter;
          // convTo = (convFrom == "G" ? "J" : "G");
          overrideConvFrom = evPrefix.prefixLetter;
          selText = evPrefix.croppedSelText;
        }

        if (overrideConvFrom == convTo) {
          alert( dateSearchCantConvert.replace(new RegExp("\%","g"), pickerFromType(convTo)) );
          return;
        }

        convTo = calTypeFromType(convTo);
        convFrom = calTypeFromType(convFrom);

        // console.log("selText "+selText);
        // console.log("from " + convFrom + " / " + "to " + convTo);

        var isSpan = false;
        if (selText.indexOf(" - ") >= 0) { isSpan=true; }
        // console.log("isSpan "+isSpan);

        var thisDateEdit = $("#dateSearchEdit");
        thisDateEdit.calendarsPicker("option", { rangeSelect: isSpan } );

        var canConvert=true;

        if (convTo=="julian") {
          thisDateEdit.val(selText);
  				var curdate=thisDateEdit.calendarsPicker('getDate');
          // console.log(curdate)
  				for (var i = 0; i < curdate.length; i++) {
  					canConvert=( (canConvert) && (canConvToJulian(curdate[i])) );
  				}
  			}

        // console.log("canConvert "+canConvert);

        if (canConvert) {
          setPickerToGregJuli(thisDateEdit, convFrom);
          thisDateEdit.val(selText);
          thisDateEdit.show().calendarsPicker("show").hide()

          var curdate = thisDateEdit.calendarsPicker('getDate');
          // console.log(curdate);

  				var toCalendar = $.calendars.instance(convTo, dateSearchLocale);
  				var newdate = new Array();

  				for (var i = 0; i < curdate.length; i++) {
  					newdate[i] = curdate[i];
  					newdate[i] = toCalendar.fromJD(curdate[i].toJD());
  				}

  				setPickerToGregJuli(thisDateEdit, convTo);
          curPrefix = prefixFromType(convTo);
  				thisDateEdit.calendarsPicker('setDate', newdate);

        }

      }
      else { alert(dateSearchSelectDate); }
    }
    else { alert(dateSearchSelectDate); }
  });

});

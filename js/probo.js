function openTeam(evt, teamName) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("probo-team-links");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById(teamName).style.display = "block";
  evt.currentTarget.className += " active";
}

function selectRepo(evt, repoName) {
  if (evt.currentTarget.value == 'disabled') {
    evt.currentTarget.value = 'enabled';
    evt.currentTarget.className += " active";
    evt.currentTarget.parentElement.className += " probo-repository-selected";
    evt.currentTarget.innerHTML = "Disable";
  }
  else {
    evt.currentTarget.value = 'disabled';
    evt.currentTarget.className = 'probo-enable-repository';
    evt.currentTarget.parentElement.className = 'probo-repository-block';
    evt.currentTarget.innerHTML = "Enable";
  }
}

function viewRepo(evt, repoUrl) {
  window.location.href = repoUrl;
}

(function ($) {
  
  Drupal.behaviors.changeTabs = {
    attach: function (context, settings) {
      $("input[type='checkbox']").change(function() {
        if ($(this).is(":checked")) {
          $(this).parent().addClass("probo-repository-selected"); 
        } else {
          $(this).parent().removeClass("probo-repository-selected");  
        }
      });
    }
  }
})(jQuery);

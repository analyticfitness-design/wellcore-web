/* ================================================
   WELLCORE FITNESS - MULTI-STEP FORM LOGIC
   Version: 1.0
   Description: Registration form with conditional steps
   ================================================ */

(function() {
  'use strict';

  // ==================== GLOBAL STATE ====================

  let currentStep = 0;
  let selectedPlan = '';
  let stepsSequence = [0, 1, 2, 3, 4, 5, 6, 7]; // Default: all steps shown until plan is selected
  let formData = {};

  // Invite mode state
  let inviteMode = false;
  let inviteCode = '';

  // Step configuration based on plan selection
  const stepsConfig = {
    'completo': [0, 1, 2, 3, 4, 5, 6, 7],      // All steps
    'entrenamiento': [0, 1, 2, 3, 4, 7],        // No nutrition steps
    'nutricion': [0, 1, 5, 6, 7],               // No training steps
    'esencial': [0, 1, 2, 3, 4, 5, 6, 7],       // WellCore Esencial
    'metodo': [0, 1, 2, 3, 4, 5, 6, 7],         // WellCore Metodo
    'elite': [0, 1, 2, 3, 4, 5, 6, 7]           // WellCore Elite
  };

  // Step names for display
  const stepNames = {
    0: 'Plan',
    1: 'Información',
    2: 'Experiencia',
    3: 'Preferencias',
    4: 'Lesiones',
    5: 'Nutrición',
    6: 'Hábitos',
    7: 'Finalizar'
  };

  // ==================== INITIALIZATION ====================

  document.addEventListener('DOMContentLoaded', function() {
    console.log('Form Logic Initialized');

    setupPlanSelection();
    setupNavigation();
    setupConditionalFields();
    initializeStepIndicators();

    // Check for invite code in URL
    checkInviteCode();
  });

  // ==================== PLAN SELECTION ====================

  function setupPlanSelection() {
    const planCards = document.querySelectorAll('.plan-card');
    const planInput = document.getElementById('planSeleccionado');

    planCards.forEach(card => {
      // Get the button inside the card
      const selectButton = card.querySelector('.btn-plan');

      selectButton.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Remove selected class from all cards
        planCards.forEach(c => c.classList.remove('selected'));

        // Add selected class to clicked card
        card.classList.add('selected');

        // Get plan value
        const plan = card.getAttribute('data-plan');
        selectedPlan = plan;
        planInput.value = plan;

        console.log('Plan selected:', plan);

        // Update steps sequence based on plan
        updateStepsSequence(plan);

        // Enable next button
        document.getElementById('nextBtn').disabled = false;
      });

      // Also allow clicking the whole card
      card.addEventListener('click', function(e) {
        // Only if not clicking the button directly
        if (e.target !== selectButton) {
          selectButton.click();
        }
      });
    });
  }

  function updateStepsSequence(plan) {
    stepsSequence = stepsConfig[plan] || stepsConfig['completo'];
    console.log('Steps sequence updated:', stepsSequence);

    // Update step indicators
    updateStepIndicators();

    // Update progress bar total
    updateProgressBar();
  }

  // ==================== INVITE CODE DETECTION ====================

  function checkInviteCode() {
    var params = new URLSearchParams(window.location.search);
    var code = params.get('invite');

    if (!code || !/^[a-f0-9]{32}$/i.test(code)) {
      // Normal flow
      showStep(0);
      return;
    }

    // Validate invite code against API
    fetch('/api/invitations/validate.php?code=' + encodeURIComponent(code))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.valid && data.plan) {
          activateInviteMode(code, data.plan);
        } else {
          // Invalid code — show normal form
          showStep(0);
        }
      })
      .catch(function() {
        // API error — show normal form
        showStep(0);
      });
  }

  function activateInviteMode(code, plan) {
    inviteMode = true;
    inviteCode = code;
    selectedPlan = plan;

    console.log('Invite mode activated: plan=' + plan + ', code=' + code.substring(0, 8) + '...');

    // Set hidden plan input
    var planInput = document.getElementById('planSeleccionado');
    if (planInput) planInput.value = plan;

    // Show invite banner
    var banner = document.getElementById('inviteBanner');
    if (banner) banner.style.display = 'block';

    var planBadge = document.getElementById('invitePlanBadge');
    if (planBadge) {
      var planNames = { esencial: 'Plan Esencial', metodo: 'Plan Metodo', elite: 'Plan Elite' };
      planBadge.textContent = planNames[plan] || plan;
    }

    // Show password section in step 7
    var pwSection = document.getElementById('invitePasswordSection');
    if (pwSection) {
      pwSection.style.display = 'block';
      // Make password fields required
      var pwField = document.getElementById('invitePassword');
      var pwConfirm = document.getElementById('invitePasswordConfirm');
      if (pwField) pwField.setAttribute('required', '');
      if (pwConfirm) pwConfirm.setAttribute('required', '');
    }

    // Skip step 0 (plan selection) — start from step 1
    stepsSequence = stepsConfig[plan] || stepsConfig['completo'];
    // Remove step 0 from sequence
    stepsSequence = stepsSequence.filter(function(s) { return s !== 0; });

    currentStep = 0; // First item in the filtered sequence (which is step 1)
    showStep(0);

    // Setup password match validation
    var pwConfirmField = document.getElementById('invitePasswordConfirm');
    if (pwConfirmField) {
      pwConfirmField.addEventListener('input', function() {
        var pw = document.getElementById('invitePassword').value;
        var errorEl = document.getElementById('passwordMatchError');
        if (this.value && this.value !== pw) {
          if (errorEl) errorEl.style.display = 'block';
        } else {
          if (errorEl) errorEl.style.display = 'none';
        }
      });
    }
  }

  // ==================== STEP NAVIGATION ====================

  function setupNavigation() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('wellcoreForm');

    // Next button
    nextBtn.addEventListener('click', function(e) {
      e.preventDefault();
      nextStep();
    });

    // Previous button
    prevBtn.addEventListener('click', function(e) {
      e.preventDefault();
      prevStep();
    });

    // Submit button
    submitBtn.addEventListener('click', function(e) {
      e.preventDefault();
      submitForm();
    });

    // Form submission (fallback)
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      submitForm();
    });
  }

  function nextStep() {
    // Validate current step
    if (!validateCurrentStep()) {
      return;
    }

    // Get current step element
    const currentStepElement = document.querySelector(`.form-step[data-step="${stepsSequence[currentStep]}"]`);

    // Save data from current step
    saveStepData(currentStepElement);

    // Move to next step
    if (currentStep < stepsSequence.length - 1) {
      currentStep++;
      showStep(currentStep);
    }
  }

  function prevStep() {
    if (currentStep > 0) {
      currentStep--;
      showStep(currentStep);
    }
  }

  function showStep(stepIndex) {
    const steps = document.querySelectorAll('.form-step');
    const actualStepNumber = stepsSequence[stepIndex];

    // Hide all steps
    steps.forEach(step => {
      step.classList.remove('active');
    });

    // Show current step
    const currentStepElement = document.querySelector(`.form-step[data-step="${actualStepNumber}"]`);
    if (currentStepElement) {
      currentStepElement.classList.add('active');

      // Scroll to top
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Update navigation buttons
    updateNavigationButtons();

    // Update progress bar
    updateProgressBar();

    // Update step indicators
    updateStepIndicators();

    console.log(`Showing step ${stepIndex} (actual step ${actualStepNumber})`);
  }

  function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    // Previous button
    if (currentStep === 0) {
      prevBtn.style.display = 'none';
    } else {
      prevBtn.style.display = 'block';
    }

    // Next vs Submit button
    if (currentStep === stepsSequence.length - 1) {
      nextBtn.style.display = 'none';
      submitBtn.style.display = 'block';
    } else {
      nextBtn.style.display = 'block';
      submitBtn.style.display = 'none';
    }

    // Disable next button on step 0 if no plan selected
    if (currentStep === 0 && !selectedPlan) {
      nextBtn.disabled = true;
    } else {
      nextBtn.disabled = false;
    }
  }

  // ==================== PROGRESS BAR ====================

  function updateProgressBar() {
    const progressBar = document.getElementById('progressBar');
    const totalSteps = stepsSequence.length;
    const progress = ((currentStep + 1) / totalSteps) * 100;

    progressBar.style.width = progress + '%';
    progressBar.setAttribute('aria-valuenow', progress);

    console.log(`Progress: ${progress.toFixed(0)}%`);
  }

  // ==================== STEP INDICATORS ====================

  function initializeStepIndicators() {
    const container = document.getElementById('stepIndicator');
    container.innerHTML = ''; // Clear existing

    // Create indicators for default steps (will update after plan selection)
    for (let i = 0; i < 8; i++) {
      const indicator = document.createElement('div');
      indicator.className = 'step-indicator';
      indicator.setAttribute('data-step', i);
      indicator.textContent = i + 1;
      container.appendChild(indicator);
    }
  }

  function updateStepIndicators() {
    const indicators = document.querySelectorAll('.step-indicator');
    const actualStepNumber = stepsSequence[currentStep];

    indicators.forEach((indicator, index) => {
      const stepNum = parseInt(indicator.getAttribute('data-step'));

      // Check if this step is in the current sequence
      const isInSequence = stepsSequence.includes(stepNum);

      if (!isInSequence) {
        // Hide indicators not in sequence
        indicator.style.display = 'none';
      } else {
        indicator.style.display = 'flex';

        // Get position in sequence
        const positionInSequence = stepsSequence.indexOf(stepNum);

        // Reset classes
        indicator.classList.remove('active', 'completed');

        // Set active, completed, or pending
        if (positionInSequence < currentStep) {
          indicator.classList.add('completed');
        } else if (positionInSequence === currentStep) {
          indicator.classList.add('active');
        }
      }
    });
  }

  // ==================== VALIDATION ====================

  function validateCurrentStep() {
    const actualStepNumber = stepsSequence[currentStep];
    const currentStepElement = document.querySelector(`.form-step[data-step="${actualStepNumber}"]`);

    if (!currentStepElement) {
      return true;
    }

    // Get all required fields in current step
    const requiredInputs = currentStepElement.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;

    requiredInputs.forEach(input => {
      // Skip validation if field is hidden or in a hidden conditional container
      const conditionalContainer = input.closest('.conditional-field');
      if (conditionalContainer && conditionalContainer.style.display === 'none') {
        return; // Skip this field
      }

      // Validate based on input type
      let fieldValid = false;

      if (input.type === 'checkbox') {
        // For checkboxes, check if at least one in group is checked
        if (input.name.includes('[]')) {
          const groupName = input.name;
          const checkedBoxes = currentStepElement.querySelectorAll(`input[name="${groupName}"]:checked`);
          fieldValid = checkedBoxes.length > 0;
        } else {
          fieldValid = input.checked;
        }
      } else if (input.type === 'radio') {
        // For radios, check if any in group is checked
        const groupName = input.name;
        const checkedRadio = currentStepElement.querySelector(`input[name="${groupName}"]:checked`);
        fieldValid = checkedRadio !== null;
      } else {
        // For text, select, textarea
        fieldValid = input.value.trim() !== '';

        // Email validation
        if (input.type === 'email' && fieldValid) {
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          fieldValid = emailRegex.test(input.value);
        }
      }

      if (!fieldValid) {
        isValid = false;
        input.classList.add('error');

        if (!firstInvalidField) {
          firstInvalidField = input;
        }
      } else {
        input.classList.remove('error');
      }
    });

    // Add validation class to form
    if (!isValid) {
      currentStepElement.classList.add('was-validated');

      // Scroll to first invalid field
      if (firstInvalidField) {
        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalidField.focus();
      }

      // Show error message
      showValidationError('Por favor completa todos los campos requeridos correctamente.');
    } else {
      currentStepElement.classList.remove('was-validated');
    }

    return isValid;
  }

  function showValidationError(message) {
    // Create or update error alert
    let errorAlert = document.getElementById('validationError');

    if (!errorAlert) {
      errorAlert = document.createElement('div');
      errorAlert.id = 'validationError';
      errorAlert.className = 'alert alert-danger mt-3';
      errorAlert.textContent = '';
      var iconSpan = document.createElement('span');
      iconSpan.style.cssText = 'color:#00D9FF;font-weight:700;margin-right:8px;';
      iconSpan.textContent = '//';
      errorAlert.appendChild(iconSpan);
      errorAlert.appendChild(document.createTextNode(message));

      const activeStep = document.querySelector('.form-step.active');
      if (activeStep) {
        activeStep.insertBefore(errorAlert, activeStep.firstChild);
      }
    } else {
      errorAlert.textContent = '';
      var iconSpan2 = document.createElement('span');
      iconSpan2.style.cssText = 'color:#00D9FF;font-weight:700;margin-right:8px;';
      iconSpan2.textContent = '//';
      errorAlert.appendChild(iconSpan2);
      errorAlert.appendChild(document.createTextNode(message));
      errorAlert.style.display = 'block';
    }

    // Auto-hide after 5 seconds
    setTimeout(() => {
      if (errorAlert) {
        errorAlert.style.display = 'none';
      }
    }, 5000);
  }

  // ==================== DATA COLLECTION ====================

  function saveStepData(stepElement) {
    if (!stepElement) return;

    // Get all form inputs in this step
    const inputs = stepElement.querySelectorAll('input, select, textarea');

    inputs.forEach(input => {
      // Skip hidden fields
      if (input.closest('.conditional-field') && input.closest('.conditional-field').style.display === 'none') {
        return;
      }

      const name = input.name;
      if (!name) return;

      // Handle different input types
      if (input.type === 'checkbox') {
        if (name.includes('[]')) {
          // Multiple checkboxes (array)
          if (!formData[name]) {
            formData[name] = [];
          }
          if (input.checked) {
            formData[name].push(input.value);
          }
        } else {
          // Single checkbox
          formData[name] = input.checked ? input.value : '';
        }
      } else if (input.type === 'radio') {
        if (input.checked) {
          formData[name] = input.value;
        }
      } else {
        // Text, select, textarea
        formData[name] = input.value;
      }
    });

    console.log('Step data saved:', formData);
  }

  function collectAllFormData() {
    const form = document.getElementById('wellcoreForm');
    const allInputs = form.querySelectorAll('input, select, textarea');
    const data = {};

    allInputs.forEach(input => {
      // Skip hidden conditional fields
      const conditionalContainer = input.closest('.conditional-field');
      if (conditionalContainer && conditionalContainer.style.display === 'none') {
        return;
      }

      const name = input.name;
      if (!name) return;

      if (input.type === 'checkbox') {
        if (name.includes('[]')) {
          // Array of checkboxes
          if (!data[name]) {
            data[name] = [];
          }
          if (input.checked) {
            data[name].push(input.value);
          }
        } else {
          // Single checkbox
          data[name] = input.checked ? input.value : 'no';
        }
      } else if (input.type === 'radio') {
        if (input.checked) {
          data[name] = input.value;
        }
      } else {
        data[name] = input.value;
      }
    });

    // Clean up array notation in keys
    Object.keys(data).forEach(key => {
      if (key.includes('[]')) {
        const cleanKey = key.replace('[]', '');
        data[cleanKey] = data[key];
        delete data[key];
      }
    });

    return data;
  }

  // ==================== FORM SUBMISSION ====================

  function submitForm() {
    // Final validation
    if (!validateCurrentStep()) {
      return;
    }

    // Extra validation for invite mode: password match
    if (inviteMode) {
      var pw = document.getElementById('invitePassword');
      var pwConfirm = document.getElementById('invitePasswordConfirm');
      if (pw && pwConfirm) {
        if (pw.value.length < 6) {
          showValidationError('La contrasena debe tener al menos 6 caracteres.');
          pw.focus();
          return;
        }
        if (pw.value !== pwConfirm.value) {
          showValidationError('Las contrasenas no coinciden.');
          pwConfirm.focus();
          return;
        }
      }
    }

    // Collect all form data
    const finalData = collectAllFormData();

    console.log('=== FORM SUBMISSION ===');
    console.log('Selected Plan:', selectedPlan);
    console.log('Invite Mode:', inviteMode);
    console.log('Complete Form Data:', finalData);

    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.classList.add('btn-loading');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando...';

    // === INVITE MODE: Redeem via API ===
    if (inviteMode && inviteCode) {
      submitInviteRedeem(finalData);
      return;
    }

    // === NORMAL MODE: Formspree ===
    const FORMSPREE_ID = 'mjgeoelp';

    fetch('https://formspree.io/f/' + FORMSPREE_ID, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ...finalData,
        _plan: selectedPlan,
        _subject: 'Nueva inscripcion Wellcore — Plan ' + selectedPlan
      })
    })
    .then(function(response) {
      if (response.ok) {
        showSuccessMessage();
        console.log('Inscripcion enviada correctamente via Formspree');
      } else {
        return response.json().then(function(data) {
          var msg = (data.errors && data.errors.map(function(e) { return e.message; }).join(', '))
                    || 'Error al enviar. Intenta de nuevo.';
          showErrorMessage(msg);
          resetSubmitButton();
        });
      }
    })
    .catch(function(error) {
      console.error('Error de red:', error);
      showErrorMessage('Sin conexion. Verifica tu internet e intenta de nuevo.');
      resetSubmitButton();
    });
  }

  function submitInviteRedeem(finalData) {
    var pw = document.getElementById('invitePassword');

    fetch('/api/invitations/redeem.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        code: inviteCode,
        nombre: finalData.nombre || '',
        email: finalData.email || '',
        password: pw ? pw.value : '',
        telefono: finalData.whatsapp || ''
      })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.ok) {
        // Store token for auto-login
        if (data.token) {
          localStorage.setItem('wc_token', data.token);
          localStorage.setItem('wc_user_type', 'client');
          localStorage.setItem('wc_client_name', finalData.nombre || '');
          localStorage.setItem('wc_client_email', finalData.email || '');
          localStorage.setItem('wc_client_plan', data.plan || '');
          localStorage.setItem('wc_client_id', data.client_code || '');
        }
        showInviteSuccess(data.plan);
      } else {
        showErrorMessage(data.error || 'Error al crear cuenta. Intenta de nuevo.');
        resetSubmitButton();
      }
    })
    .catch(function(error) {
      console.error('Redeem error:', error);
      showErrorMessage('Sin conexion. Verifica tu internet e intenta de nuevo.');
      resetSubmitButton();
    });
  }

  function showInviteSuccess(plan) {
    // Hide form
    var form = document.getElementById('wellcoreForm');
    if (form) form.style.display = 'none';

    var prevBtn = document.getElementById('prevBtn');
    var nextBtn = document.getElementById('nextBtn');
    var submitBtn = document.getElementById('submitBtn');
    if (prevBtn) prevBtn.style.display = 'none';
    if (nextBtn) nextBtn.style.display = 'none';
    if (submitBtn) submitBtn.style.display = 'none';

    var stepIndicator = document.getElementById('stepIndicator');
    var progressBar = document.getElementById('progressBar');
    if (stepIndicator) stepIndicator.style.display = 'none';
    if (progressBar && progressBar.parentElement) progressBar.parentElement.style.display = 'none';

    var banner = document.getElementById('inviteBanner');
    if (banner) banner.style.display = 'none';

    // Show custom success for invite
    var successEl = document.getElementById('successMessage');
    if (successEl) {
      // Clear and rebuild with invite-specific content
      successEl.textContent = '';

      var icon = document.createElement('div');
      icon.style.cssText = 'width:64px;height:64px;background:rgba(0,217,255,0.12);border:1px solid #00D9FF;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px';
      var iconText = document.createElement('span');
      iconText.style.cssText = 'color:#00D9FF;font-weight:700;font-size:24px';
      iconText.textContent = '//';
      icon.appendChild(iconText);

      var h2 = document.createElement('h2');
      h2.style.cssText = "font-family:'Bebas Neue',sans-serif;font-size:48px;color:#fff;letter-spacing:2px;margin-bottom:16px";
      h2.textContent = 'CUENTA CREADA';

      var sub = document.createElement('p');
      sub.style.cssText = "font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:2px;color:#00D9FF;text-transform:uppercase;margin-bottom:8px";
      sub.textContent = 'Beta Tester — Plan ' + (plan || '').toUpperCase();

      var msg = document.createElement('p');
      msg.style.cssText = 'font-size:14px;color:rgba(255,255,255,0.55);max-width:500px;margin:0 auto 32px';
      msg.textContent = 'Tu cuenta ha sido creada exitosamente. Ya puedes acceder al portal de clientes con tu email y contrasena.';

      var link = document.createElement('a');
      link.href = '/login.html';
      link.style.cssText = "display:inline-flex;align-items:center;gap:8px;background:#E31E24;color:#fff;font-family:'Inter',sans-serif;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;padding:14px 28px;border-radius:0;border:4px solid #E31E24;text-decoration:none";
      link.textContent = 'Ir al Login';

      successEl.appendChild(icon);
      successEl.appendChild(h2);
      successEl.appendChild(sub);
      successEl.appendChild(msg);
      successEl.appendChild(link);
      successEl.style.display = 'block';
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function showSuccessMessage() {
    // Hide form
    var form = document.getElementById('wellcoreForm');
    if (form) form.style.display = 'none';

    // Hide navigation buttons
    var prevBtn = document.getElementById('prevBtn');
    var nextBtn = document.getElementById('nextBtn');
    var submitBtn = document.getElementById('submitBtn');
    if (prevBtn) prevBtn.style.display = 'none';
    if (nextBtn) nextBtn.style.display = 'none';
    if (submitBtn) submitBtn.style.display = 'none';

    // Hide step indicator and progress bar
    var stepIndicator = document.getElementById('stepIndicator');
    var progressBar = document.getElementById('progressBar');
    if (stepIndicator) stepIndicator.style.display = 'none';
    if (progressBar && progressBar.parentElement) progressBar.parentElement.style.display = 'none';

    // Show success message
    var successMessage = document.getElementById('successMessage');
    if (successMessage) successMessage.style.display = 'block';

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Optional: Send confirmation email
    // sendConfirmationEmail(formData.email);

    // Optional: Track conversion
    // trackConversion('registration_complete', selectedPlan);
  }

  function showErrorMessage(message) {
    const errorAlert = document.createElement('div');
    errorAlert.className = 'alert alert-danger mt-3';
    errorAlert.textContent = '';
    var errIcon = document.createElement('span');
    errIcon.style.cssText = 'color:#00D9FF;font-weight:700;margin-right:8px;';
    errIcon.textContent = '//';
    errorAlert.appendChild(errIcon);
    errorAlert.appendChild(document.createTextNode(message));

    const activeStep = document.querySelector('.form-step.active');
    if (activeStep) {
      activeStep.insertBefore(errorAlert, activeStep.firstChild);
    }

    // Auto-hide after 5 seconds
    setTimeout(() => {
      errorAlert.remove();
    }, 5000);
  }

  function resetSubmitButton() {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.classList.remove('btn-loading');
    submitBtn.disabled = false;
    submitBtn.textContent = '\u2713 Completar Inscripcion';
  }

  // ==================== CONDITIONAL FIELDS ====================

  function setupConditionalFields() {
    // Objetivo Otro
    const objetivoSelect = document.getElementById('objetivo_principal');
    if (objetivoSelect) {
      objetivoSelect.addEventListener('change', function() {
        const otroContainer = document.getElementById('objetivo_otro_container');
        if (this.value === 'otro') {
          otroContainer.style.display = 'block';
          document.getElementById('objetivo_otro').required = true;
        } else {
          otroContainer.style.display = 'none';
          document.getElementById('objetivo_otro').required = false;
        }
      });
    }

    // Lesiones - Show description if any injury selected (except "ninguna")
    const lesionChecks = document.querySelectorAll('.lesion-check');
    lesionChecks.forEach(check => {
      check.addEventListener('change', function() {
        const ningunaCheck = document.getElementById('lesion_ninguna');
        const descripcionContainer = document.getElementById('lesiones_descripcion_container');

        if (this.id === 'lesion_ninguna' && this.checked) {
          // Uncheck all others if "ninguna" is checked
          lesionChecks.forEach(c => {
            if (c.id !== 'lesion_ninguna') {
              c.checked = false;
            }
          });
          descripcionContainer.style.display = 'none';
          document.getElementById('lesiones_descripcion').required = false;
        } else if (this.checked && this.id !== 'lesion_ninguna') {
          // Uncheck "ninguna" if any other is checked
          ningunaCheck.checked = false;
          descripcionContainer.style.display = 'block';
          document.getElementById('lesiones_descripcion').required = true;
        } else {
          // Check if any injury is still checked
          const anyChecked = Array.from(lesionChecks).some(c =>
            c.checked && c.id !== 'lesion_ninguna'
          );
          if (!anyChecked) {
            descripcionContainer.style.display = 'none';
            document.getElementById('lesiones_descripcion').required = false;
          }
        }
      });
    });

    // Condiciones Médicas
    const condicionesRadios = document.querySelectorAll('input[name="condiciones_medicas"]');
    condicionesRadios.forEach(radio => {
      radio.addEventListener('change', function() {
        const descripcionContainer = document.getElementById('condiciones_descripcion_container');
        if (this.value === 'si') {
          descripcionContainer.style.display = 'block';
          document.getElementById('condiciones_descripcion').required = true;
        } else {
          descripcionContainer.style.display = 'none';
          document.getElementById('condiciones_descripcion').required = false;
        }
      });
    });

    // Mamoplastia (show only for women)
    const generoRadios = document.querySelectorAll('input[name="genero"]');
    generoRadios.forEach(radio => {
      radio.addEventListener('change', function() {
        const mamoplastiaContainer = document.getElementById('mamoplastia_container');
        if (this.value === 'femenino') {
          mamoplastiaContainer.style.display = 'block';
        } else {
          mamoplastiaContainer.style.display = 'none';
        }
      });
    });

    // Alergias
    const alergiasRadios = document.querySelectorAll('input[name="alergias"]');
    alergiasRadios.forEach(radio => {
      radio.addEventListener('change', function() {
        const descripcionContainer = document.getElementById('alergias_descripcion_container');
        if (this.value === 'si') {
          descripcionContainer.style.display = 'block';
          document.getElementById('alergias_descripcion').required = true;
        } else {
          descripcionContainer.style.display = 'none';
          document.getElementById('alergias_descripcion').required = false;
        }
      });
    });

    // Restricción Dietaria - Otra
    const restriccionSelect = document.getElementById('restriccion_dieta');
    if (restriccionSelect) {
      restriccionSelect.addEventListener('change', function() {
        const otraContainer = document.getElementById('restriccion_otra_container');
        if (this.value === 'otra') {
          otraContainer.style.display = 'block';
          document.getElementById('restriccion_otra').required = true;
        } else {
          otraContainer.style.display = 'none';
          document.getElementById('restriccion_otra').required = false;
        }
      });
    }

    // Suplementos
    const suplementosRadios = document.querySelectorAll('input[name="suplementos"]');
    suplementosRadios.forEach(radio => {
      radio.addEventListener('change', function() {
        const listaContainer = document.getElementById('suplementos_lista_container');
        if (this.value === 'si') {
          listaContainer.style.display = 'block';
        } else {
          listaContainer.style.display = 'none';
        }
      });
    });
  }

  // ==================== UTILITY FUNCTIONS ====================

  /**
   * Get current step name for display
   */
  function getCurrentStepName() {
    const actualStepNumber = stepsSequence[currentStep];
    return stepNames[actualStepNumber] || 'Paso ' + (currentStep + 1);
  }

  /**
   * Export form data as JSON (for debugging)
   */
  window.exportFormData = function() {
    const data = collectAllFormData();
    console.log('Form Data Export:');
    console.log(JSON.stringify(data, null, 2));

    // Create downloadable JSON file
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(data, null, 2));
    const downloadAnchorNode = document.createElement('a');
    downloadAnchorNode.setAttribute("href", dataStr);
    downloadAnchorNode.setAttribute("download", "wellcore_form_data.json");
    document.body.appendChild(downloadAnchorNode);
    downloadAnchorNode.click();
    downloadAnchorNode.remove();
  };

  /**
   * Reset form (for testing)
   */
  window.resetForm = function() {
    if (confirm('¿Estás seguro de que quieres reiniciar el formulario? Se perderán todos los datos.')) {
      document.getElementById('wellcoreForm').reset();
      currentStep = 0;
      selectedPlan = '';
      formData = {};
      stepsSequence = [];

      document.querySelectorAll('.plan-card').forEach(card => {
        card.classList.remove('selected');
      });

      showStep(0);
      console.log('Form reset');
    }
  };

  // ==================== CONSOLE CREDITS ====================

  console.log('%c Wellcore Fitness - Form Logic Loaded ', 'background: #E31E24; color: white; font-size: 14px; font-weight: bold; padding: 5px;');
  console.log('%c Multi-step form with conditional logic active ', 'color: #666; font-size: 11px;');
  console.log('%c Debug commands: exportFormData(), resetForm() ', 'color: #999; font-size: 10px; font-style: italic;');

})();

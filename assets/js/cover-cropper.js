/**
 * Cover image cropper helper (uses Cropper.js when available).
 * Attaches to <input type="file" name="cover_image"> and stores a cropped Blob
 * that submit handlers can add to FormData.
 */

(function () {
  const croppedByInput = new WeakMap();

  function ensureModalElements() {
    let modal = document.getElementById('coverCropModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'coverCropModal';
    modal.className = 'custom-modal';
    modal.style.display = 'none';
    modal.innerHTML = `
      <div class="custom-modal-overlay" data-cover-crop-close="1"></div>
      <div class="custom-modal-content custom-modal-content-wide" role="dialog" aria-modal="true" aria-labelledby="coverCropTitle">
        <div class="custom-modal-header">
          <h3 class="custom-modal-title" id="coverCropTitle">Adjust cover image</h3>
          <button type="button" class="custom-modal-close" data-cover-crop-close="1" aria-label="Close">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="custom-modal-body">
          <div class="cover-cropper-toolbar">
            <label class="form-label" for="coverCropAspect" style="margin:0;">Aspect</label>
            <select id="coverCropAspect" class="form-select cover-cropper-aspect">
              <option value="3/1" selected>Wide (3:1)</option>
              <option value="16/9">Standard (16:9)</option>
              <option value="1/1">Square (1:1)</option>
              <option value="free">Free</option>
            </select>
            <div class="cover-cropper-actions">
              <button type="button" class="btn btn-secondary btn-small" id="coverCropZoomOut" title="Zoom out">
                <i class="fas fa-search-minus"></i>
              </button>
              <button type="button" class="btn btn-secondary btn-small" id="coverCropZoomIn" title="Zoom in">
                <i class="fas fa-search-plus"></i>
              </button>
              <button type="button" class="btn btn-secondary btn-small" id="coverCropReset" title="Reset">
                <i class="fas fa-undo"></i>
              </button>
            </div>
          </div>
          <div class="cover-cropper-stage">
            <img id="coverCropImage" class="cover-cropper-image" alt="Cover image to crop">
          </div>
          <div class="form-help" style="margin-top:0.75rem;">
            Drag to reposition. Use zoom to frame the cover.
          </div>
        </div>
        <div class="custom-modal-footer">
          <button type="button" class="btn btn-secondary" id="coverCropCancel">Cancel</button>
          <button type="button" class="btn" id="coverCropApply">Use this cover</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);
    return modal;
  }

  function showModal(modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    // trigger animation
    setTimeout(() => modal.classList.add('show'), 10);
  }

  function hideModal(modal) {
    modal.classList.remove('show');
    setTimeout(() => {
      modal.style.display = 'none';
      document.body.style.overflow = '';
    }, 250);
  }

  function parseAspect(value) {
    if (value === 'free') return NaN;
    const parts = value.split('/');
    if (parts.length !== 2) return NaN;
    const a = Number(parts[0]);
    const b = Number(parts[1]);
    if (!Number.isFinite(a) || !Number.isFinite(b) || b === 0) return NaN;
    return a / b;
  }

  function getOutputSize(aspectRatio) {
    // Keep uploads reasonable and crisp.
    const maxWidth = 1800;
    if (!Number.isFinite(aspectRatio) || aspectRatio <= 0) {
      return { maxWidth: 1800, maxHeight: 1800 };
    }
    const width = maxWidth;
    const height = Math.round(width / aspectRatio);
    return { width, height };
  }

  function ensurePreview(input) {
    let preview = input.parentElement?.querySelector('.cover-image-preview');
    if (preview) return preview;
    preview = document.createElement('div');
    preview.className = 'cover-image-preview';
    preview.style.display = 'none';
    input.insertAdjacentElement('afterend', preview);
    return preview;
  }

  async function openCropperForInput(input) {
    const file = input.files && input.files[0];
    if (!file) return;

    if (typeof Cropper === 'undefined') {
      // If cropper isn't available, fall back to raw upload.
      return;
    }

    const modal = ensureModalElements();
    const img = modal.querySelector('#coverCropImage');
    const aspectSelect = modal.querySelector('#coverCropAspect');
    const btnZoomIn = modal.querySelector('#coverCropZoomIn');
    const btnZoomOut = modal.querySelector('#coverCropZoomOut');
    const btnReset = modal.querySelector('#coverCropReset');
    const btnCancel = modal.querySelector('#coverCropCancel');
    const btnApply = modal.querySelector('#coverCropApply');

    // Load image into cropper
    const objectUrl = URL.createObjectURL(file);
    img.src = objectUrl;

    // Ensure image is loaded before constructing Cropper
    await new Promise((resolve) => {
      if (img.complete) return resolve();
      img.onload = () => resolve();
      img.onerror = () => resolve();
    });

    // Cleanup any previous cropper instance
    if (img._coverCropperInstance) {
      try {
        img._coverCropperInstance.destroy();
      } catch (e) {}
      img._coverCropperInstance = null;
    }

    const initialAspect = parseAspect(aspectSelect.value);
    const cropper = new Cropper(img, {
      viewMode: 2,
      dragMode: 'move',
      autoCropArea: 1,
      background: false,
      responsive: true,
      movable: true,
      zoomable: true,
      rotatable: false,
      scalable: false,
      aspectRatio: initialAspect
    });
    img._coverCropperInstance = cropper;

    const closeEls = modal.querySelectorAll('[data-cover-crop-close="1"]');
    const onClose = () => {
      // Restore input if user cancels (keep original file selection).
      hideModal(modal);
      try {
        cropper.destroy();
      } catch (e) {}
      img._coverCropperInstance = null;
      URL.revokeObjectURL(objectUrl);
      // Reset input so a user can re-select the same file to reopen cropper.
      input.value = '';
    };

    closeEls.forEach((el) => el.addEventListener('click', onClose, { once: true }));
    btnCancel.addEventListener('click', onClose, { once: true });

    aspectSelect.onchange = () => {
      cropper.setAspectRatio(parseAspect(aspectSelect.value));
    };
    btnZoomIn.onclick = () => cropper.zoom(0.1);
    btnZoomOut.onclick = () => cropper.zoom(-0.1);
    btnReset.onclick = () => cropper.reset();

    btnApply.onclick = () => {
      const aspect = parseAspect(aspectSelect.value);
      const canvasOpts = getOutputSize(aspect);

      let canvas;
      try {
        canvas = cropper.getCroppedCanvas(canvasOpts);
      } catch (e) {
        canvas = cropper.getCroppedCanvas();
      }
      if (!canvas) {
        onClose();
        return;
      }

      const filename = 'cover.jpg';
      canvas.toBlob(
        (blob) => {
          if (!blob) {
            onClose();
            return;
          }

          croppedByInput.set(input, { blob, filename });

          const preview = ensurePreview(input);
          const previewUrl = URL.createObjectURL(blob);
          preview.style.display = 'block';
          preview.style.backgroundImage = `url(${previewUrl})`;
          preview.dataset.objectUrl = previewUrl;

          hideModal(modal);
          try {
            cropper.destroy();
          } catch (e) {}
          img._coverCropperInstance = null;
          URL.revokeObjectURL(objectUrl);

          // Clear input so raw file isn't included in FormData automatically.
          input.value = '';
        },
        'image/jpeg',
        0.9
      );
    };

    showModal(modal);
  }

  function init() {
    // Attach to any cover image inputs present on the page.
    const inputs = document.querySelectorAll('input[type="file"][name="cover_image"]');
    inputs.forEach((input) => {
      if (input.dataset.coverCropperAttached === 'true') return;
      input.dataset.coverCropperAttached = 'true';

      input.addEventListener('change', () => {
        // Revoke previous preview URL (if any)
        const preview = input.parentElement?.querySelector('.cover-image-preview');
        if (preview && preview.dataset.objectUrl) {
          try { URL.revokeObjectURL(preview.dataset.objectUrl); } catch (e) {}
          delete preview.dataset.objectUrl;
        }
        openCropperForInput(input);
      });
    });
  }

  // Expose a minimal API for submit handlers to use.
  window.coverCropper = {
    init,
    getCroppedForInput(input) {
      if (!input) return null;
      return croppedByInput.get(input) || null;
    },
    clearForInput(input) {
      if (!input) return;
      croppedByInput.delete(input);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();


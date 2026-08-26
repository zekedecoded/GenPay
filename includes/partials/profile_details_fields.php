<?php
/**
 * Partial: the personal-details form fields — sex, date of birth, derived age,
 * and address. Shared by every profile edit form (student, parent) so the
 * fields, their order and their labels stay identical across roles; the columns
 * behind them come from gjc_user_profile_columns() and the values are read back
 * out by gjc_collect_profile_details().
 *
 * Requires:
 *   $pdRow   array  the user's `users` row, for the current values
 *   $pdField string wrapper class for one field  ('pf-field' | 'pfield')
 *   $pdGrid  string wrapper class for a 2-up row ('pf-form-grid' | 'pgrid')
 *
 * Age is a field here but never an input: it is disabled, carries no `name`, and
 * so is never submitted. It exists to show the user what their birth date works
 * out to. There is no age column — see gjc_age_from_dob().
 *
 * Layout rule: a field is either half-width inside a complete $pdGrid pair, or
 * full-width on its own row. Never a lone half-width field — that leaves a hole
 * in the grid and the column edges stop lining up with the rows above.
 * Paired fields also carry a matching <small> line (blank where there is
 * nothing to say) so both cells in a row end at the same height.
 *
 * The caller supplies its own surrounding card/panel and its own <form>, which
 * is why this file emits fields only.
 */
$pdRow   = $pdRow   ?? [];
$pdField = $pdField ?? 'pf-field';
$pdGrid  = $pdGrid  ?? 'pf-form-grid';

$pdEsc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// A rejected submit re-renders the form, so prefer what was just typed over
// what is still in the database — otherwise a validation error silently throws
// away everything else the user had filled in.
$pdVal = static fn(string $key): string => $pdEsc($_POST[$key] ?? $pdRow[$key] ?? '');

$pdSex = (string) ($_POST['sex'] ?? $pdRow['sex'] ?? '');
$pdDob = (string) ($_POST['date_of_birth'] ?? $pdRow['date_of_birth'] ?? '');
$pdAge = gjc_age_from_dob($pdDob);
?>

<div class="<?= $pdEsc($pdField) ?>">
    <label for="pdSex">Sex</label>
    <select name="sex" id="pdSex">
        <option value="">Not specified</option>
        <?php foreach (gjc_sex_options() as $pdOption): ?>
        <option value="<?= $pdEsc($pdOption) ?>" <?= $pdSex === $pdOption ? 'selected' : '' ?>>
            <?= $pdEsc($pdOption) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="<?= $pdEsc($pdGrid) ?>">
    <div class="<?= $pdEsc($pdField) ?>">
        <label for="pdDob">Date of Birth</label>
        <input type="date" name="date_of_birth" id="pdDob"
               value="<?= $pdEsc($pdDob) ?>"
               max="<?= date('Y-m-d') ?>">
        <small>&nbsp;</small>
    </div>

    <div class="<?= $pdEsc($pdField) ?>">
        <label for="pdAge">Age</label>
        <?php // No name attribute: this is never submitted. Age is worked out
              // from the date beside it, so there is nothing here to save. ?>
        <input type="text" id="pdAge" disabled
               value="<?= $pdAge !== null ? (int) $pdAge : '' ?>"
               placeholder="—">
        <small>Worked out from your date of birth.</small>
    </div>
</div>

<p class="pd-subhead">Address</p>

<div class="<?= $pdEsc($pdGrid) ?>">
    <div class="<?= $pdEsc($pdField) ?>">
        <label for="pdBarangay">Barangay</label>
        <input type="text" name="address_barangay" id="pdBarangay" maxlength="120"
               value="<?= $pdVal('address_barangay') ?>" placeholder="e.g. San Fernando Sur">
        <small>&nbsp;</small>
    </div>

    <div class="<?= $pdEsc($pdField) ?>">
        <label for="pdCity">City / Municipality</label>
        <input type="text" name="address_city" id="pdCity" maxlength="120"
               value="<?= $pdVal('address_city') ?>" placeholder="e.g. Cabiao">
        <small>&nbsp;</small>
    </div>
</div>

<div class="<?= $pdEsc($pdField) ?>">
    <label for="pdProvince">Province</label>
    <input type="text" name="address_province" id="pdProvince" maxlength="120"
           value="<?= $pdVal('address_province') ?>" placeholder="e.g. Nueva Ecija">
    <small>Barangay, municipality and province only — no house number needed.</small>
</div>

<script>
// Keep the Age box in step with the date picker as it is edited, so the user
// sees the same number the server will derive once they save. Purely cosmetic:
// the field is disabled and never submitted, and the server recomputes on read.
(function () {
    var dob = document.getElementById("pdDob");
    var age = document.getElementById("pdAge");
    if (!dob || !age) {
        return;
    }
    dob.addEventListener("input", function () {
        var born = new Date(dob.value);
        if (!dob.value || isNaN(born.getTime())) {
            age.value = "";
            return;
        }
        var today = new Date();
        var years = today.getFullYear() - born.getFullYear();
        var m = today.getMonth() - born.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < born.getDate())) {
            years--;
        }
        age.value = years >= 0 ? years : "";
    });
})();
</script>
